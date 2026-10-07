<?php

namespace App\Support;

use App\Models\Buyer;
use App\Models\Conversation;
use App\Models\Employee;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\PrsItem;
use App\Models\ScreenMessage;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserActivitySubject
{
    /**
     * Attribute names checked (in order) when building a human-readable subject code.
     *
     * @var list<string>
     */
    private const SUBJECT_CODE_ATTRIBUTES = [
        'code',
        'item_code',
        'product_code',
        'ts_number',
        'po_number',
        'prs_number',
        'sws_number',
        'rr_number',
        'sa_number',
        'dr_number',
        'obc_number',
        'doc_number',
        'title',
        'display_code',
        'name',
    ];

    /**
     * Request input keys that may carry a document/master code on create.
     *
     * @var list<string>
     */
    private const CREATE_INPUT_CODE_KEYS = [
        'sws_number',
        'po_number',
        'prs_number',
        'rr_number',
        'ts_number',
        'sa_number',
        'obc_number',
        'dr_number',
        'doc_number',
        'code',
        'employee_id',
        'code_employee',
        'employee_name',
        'name',
        'title',
    ];

    /**
     * @return array{
     *     subject?: string,
     *     subject_type?: string,
     *     subject_id?: int|string,
     *     subject_code?: string
     * }
     */
    public static function resolve(Request $request, ?User $actor = null): array
    {
        $routeName = (string) $request->route()?->getName();
        $actor ??= $request->user();

        if (in_array($routeName, [
            'chat.direct-messages.store',
            'chat.conversations.store',
            'chat.typing',
        ], true)) {
            $peerId = (int) $request->input('user_id');

            if ($peerId > 0) {
                $peer = User::query()->find($peerId);

                return self::personSubject($peerId, $peer?->name, 'user');
            }
        }

        if ($routeName === 'chat.messages.store' || $routeName === 'chat.messages.index') {
            $conversation = $request->route('conversation');

            if ($conversation instanceof Conversation && $actor instanceof User) {
                $peer = $conversation->otherParticipant($actor);

                if ($peer !== null) {
                    return self::personSubject($peer->id, $peer->name, 'user');
                }

                return self::fromModel($conversation, 'conversation');
            }
        }

        $parameters = $request->route()?->parameters() ?? [];

        foreach ($parameters as $name => $value) {
            if ($value instanceof Model) {
                // Route models are resolved before the controller mutates them.
                if (in_array($request->method(), ['PUT', 'PATCH'], true) && $value->exists) {
                    $value->refresh();
                }

                return self::fromModel($value, (string) $name);
            }
        }

        foreach ($parameters as $name => $value) {
            if (is_object($value) || $value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value) && ! (is_string($value) && ctype_digit($value))) {
                continue;
            }

            $key = is_numeric($value) ? $value + 0 : (int) $value;
            $normalized = str_replace('-', '_', strtolower((string) $name));
            $compact = str_replace('_', '', $normalized);

            if (in_array($normalized, ['store_withdrawal', 'storewithdrawal'], true)
                || in_array($compact, ['storewithdrawal'], true)) {
                return self::fromStoreWithdrawalId($key, (string) $name);
            }

            $model = self::resolveModelForParameter((string) $name, $key, $routeName);

            if ($model instanceof Model) {
                return self::fromModel($model, (string) $name);
            }

            return [
                'subject' => '#'.$key,
                'subject_type' => (string) $name,
                'subject_id' => $key,
            ];
        }

        return [];
    }

    /**
     * Lightweight create enrichment from request input only (no DB lookup).
     *
     * @return array{
     *     subject?: string,
     *     subject_type?: string,
     *     subject_code?: string
     * }
     */
    public static function resolveFromCreateInput(Request $request): array
    {
        $code = self::extractCreateInputCode($request);

        if ($code === null) {
            return [];
        }

        $routeName = (string) $request->route()?->getName();
        $type = self::subjectTypeFromStoreRoute($routeName);

        return array_filter([
            'subject' => '('.$code.')',
            'subject_type' => $type,
            'subject_code' => $code,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array{
     *     subject: string,
     *     subject_type: string,
     *     subject_id: int|string,
     *     subject_code?: string
     * }
     */
    public static function fromModel(Model $model, string $type): array
    {
        $key = $model->getKey();

        if ($model instanceof User) {
            return self::personSubject((int) $key, $model->name, $type);
        }

        if ($model instanceof Employee) {
            return self::employeeSubject($model, $type);
        }

        if ($model instanceof PrsItem) {
            return self::prsItemSubject($model, $type);
        }

        $code = self::extractCode($model);

        return array_filter([
            'subject' => $code !== null && $code !== ''
                ? '#'.$key.' ('.$code.')'
                : '#'.$key,
            'subject_type' => $type,
            'subject_id' => $key,
            'subject_code' => $code,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public static function isPrintOrDocumentReportRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        if (str_ends_with($routeName, '.print') || str_ends_with($routeName, '.report')) {
            return true;
        }

        $segment = str_replace('-', '_', (string) last(explode('.', $routeName)));

        return in_array($segment, ['print', 'report'], true);
    }

    public static function isGeneratedReportRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        if (! str_contains($routeName, '.reports.')) {
            return false;
        }

        return ! str_ends_with($routeName, '.index') && ! str_ends_with($routeName, '.print');
    }

    /**
     * @return array{
     *     subject: string,
     *     subject_type: string,
     *     subject_id: int|string,
     *     subject_code?: string
     * }
     */
    private static function fromStoreWithdrawalId(int|string $id, string $type): array
    {
        $swsNumber = DB::table('store_withdrawals')
            ->where('id', $id)
            ->value('sws_number');
        $code = is_string($swsNumber) ? trim($swsNumber) : '';

        return array_filter([
            'subject' => $code !== '' ? '#'.$id.' ('.$code.')' : '#'.$id,
            'subject_type' => $type,
            'subject_id' => $id,
            'subject_code' => $code !== '' ? $code : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array{
     *     subject: string,
     *     subject_type: string,
     *     subject_id: int|string,
     *     subject_code?: string
     * }
     */
    private static function employeeSubject(Employee $employee, string $type): array
    {
        $key = $employee->getKey();
        $code = trim((string) ($employee->code_employee ?: $employee->employee_id));
        $name = trim((string) $employee->employee_name);

        $label = match (true) {
            $code !== '' && $name !== '' => $code.' · '.$name,
            $code !== '' => $code,
            $name !== '' => $name,
            default => '',
        };

        return array_filter([
            'subject' => $label !== '' ? '#'.$key.' ('.$label.')' : '#'.$key,
            'subject_type' => $type,
            'subject_id' => $key,
            'subject_code' => $label !== '' ? $label : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array{
     *     subject: string,
     *     subject_type: string,
     *     subject_id: int|string,
     *     subject_code?: string
     * }
     */
    private static function prsItemSubject(PrsItem $prsItem, string $type): array
    {
        $prsItem->loadMissing([
            'prs:id,prs_number',
            'item:id,code',
        ]);

        $key = $prsItem->getKey();
        $prsNumber = trim((string) ($prsItem->prs?->prs_number ?? ''));
        $itemCode = trim((string) ($prsItem->item?->code ?? ''));

        $label = match (true) {
            $prsNumber !== '' && $itemCode !== '' => $prsNumber.' · '.$itemCode,
            $prsNumber !== '' => $prsNumber,
            $itemCode !== '' => $itemCode,
            default => '',
        };

        return array_filter([
            'subject' => $label !== '' ? '#'.$key.' ('.$label.')' : '#'.$key,
            'subject_type' => $type,
            'subject_id' => $key,
            'subject_code' => $label !== '' ? $label : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private static function resolveModelForParameter(string $name, int|string $key, string $routeName): ?Model
    {
        $normalized = str_replace('-', '_', strtolower($name));

        $map = [
            'product' => Item::class,
            'item' => Item::class,
            'currency' => \App\Models\Currency::class,
            'purchase_order' => \App\Models\PurchaseOrder::class,
            'purchaseorder' => \App\Models\PurchaseOrder::class,
            'transfer_slip' => \App\Models\TransferSlip::class,
            'transferslip' => \App\Models\TransferSlip::class,
            'prs' => \App\Models\Prs::class,
            'prs_item' => PrsItem::class,
            'prsitem' => PrsItem::class,
            'supplier' => \App\Models\Supplier::class,
            'buyer' => Buyer::class,
            'receiving_report' => \App\Models\ReceivingReport::class,
            'receivingreport' => \App\Models\ReceivingReport::class,
            'delivery' => \App\Models\Delivery::class,
            'screen_message' => ScreenMessage::class,
            'screenmessage' => ScreenMessage::class,
            'employee' => Employee::class,
            'unit_of_measurement' => UnitOfMeasure::class,
            'unitofmeasurement' => UnitOfMeasure::class,
            'product_category' => ItemCategory::class,
            'productcategory' => ItemCategory::class,
        ];

        if (str_starts_with($routeName, 'product.')) {
            return Item::query()->find($key);
        }

        if (isset($map[$normalized]) && $map[$normalized] !== null) {
            return $map[$normalized]::query()->find($key);
        }

        $compact = str_replace('_', '', $normalized);
        if (isset($map[$compact]) && $map[$compact] !== null) {
            return $map[$compact]::query()->find($key);
        }

        $guesses = [
            'App\\Models\\'.Str::studly($normalized),
            'App\\Models\\'.Str::studly(Str::singular($normalized)),
        ];

        foreach ($guesses as $class) {
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                return $class::query()->find($key);
            }
        }

        return null;
    }

    private static function extractCode(Model $model): ?string
    {
        foreach (self::SUBJECT_CODE_ATTRIBUTES as $attribute) {
            if ($attribute === 'name' && ! ($model instanceof User || $model instanceof Buyer)) {
                continue;
            }

            if ($attribute === 'title' && ! $model instanceof ScreenMessage) {
                continue;
            }

            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value) && (string) $value !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private static function extractCreateInputCode(Request $request): ?string
    {
        $employeeId = trim((string) $request->input('employee_id', ''));
        $employeeName = trim((string) $request->input('employee_name', ''));
        $codeEmployee = trim((string) $request->input('code_employee', ''));

        if ($employeeId !== '' || $codeEmployee !== '' || $employeeName !== '') {
            $code = $codeEmployee !== '' ? $codeEmployee : $employeeId;

            return match (true) {
                $code !== '' && $employeeName !== '' => $code.' · '.$employeeName,
                $code !== '' => $code,
                $employeeName !== '' => $employeeName,
                default => null,
            };
        }

        foreach (self::CREATE_INPUT_CODE_KEYS as $key) {
            if (in_array($key, ['employee_id', 'code_employee', 'employee_name'], true)) {
                continue;
            }

            $value = $request->input($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private static function subjectTypeFromStoreRoute(string $routeName): ?string
    {
        if ($routeName === '' || ! str_ends_with($routeName, '.store')) {
            return null;
        }

        $parts = explode('.', $routeName);
        array_pop($parts);

        if ($parts === []) {
            return null;
        }

        return str_replace('-', '_', implode('.', $parts));
    }

    /**
     * @return array{
     *     subject: string,
     *     subject_type: string,
     *     subject_id: int,
     *     subject_code?: string
     * }
     */
    private static function personSubject(int $id, ?string $name, string $type): array
    {
        $name = trim((string) $name);

        return array_filter([
            'subject' => $name !== '' ? '#'.$id.' '.$name : '#'.$id,
            'subject_type' => $type,
            'subject_id' => $id,
            'subject_code' => $name !== '' ? $name : null,
        ], static fn ($value) => $value !== null && $value !== '');
    }
}
