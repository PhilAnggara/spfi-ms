<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
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
        'name',
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
        $code = self::extractCode($model);

        if ($model instanceof User) {
            return self::personSubject((int) $key, $model->name, $type);
        }

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
            'supplier' => \App\Models\Supplier::class,
            'buyer' => \App\Models\Buyer::class,
            'receiving_report' => \App\Models\ReceivingReport::class,
            'receivingreport' => \App\Models\ReceivingReport::class,
            'delivery' => \App\Models\Delivery::class,
            'screen_message' => \App\Models\ScreenMessage::class,
            'screenmessage' => \App\Models\ScreenMessage::class,
            'store_withdrawal' => self::storeWithdrawalClass(),
            'storewithdrawal' => self::storeWithdrawalClass(),
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

    /**
     * @return class-string<Model>|null
     */
    private static function storeWithdrawalClass(): ?string
    {
        foreach ([
            'App\\Models\\StoreWithdrawal',
            'App\\Models\\StoresWithdrawal',
            'App\\Models\\StoreWithdrawalSlip',
        ] as $class) {
            if (class_exists($class) && is_subclass_of($class, Model::class)) {
                return $class;
            }
        }

        return null;
    }

    private static function extractCode(Model $model): ?string
    {
        foreach (self::SUBJECT_CODE_ATTRIBUTES as $attribute) {
            if ($attribute === 'name' && ! $model instanceof User) {
                continue;
            }

            $value = $model->getAttribute($attribute);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
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
