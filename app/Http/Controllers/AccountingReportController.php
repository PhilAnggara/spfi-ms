<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function index()
    {
        return view('pages.accounting-reports');
    }

    public function stockCard(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Stock Card report - Implementation pending']);
    }

    public function transaction(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Transaction report - Implementation pending']);
    }

    public function restatement(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Restatement report - Implementation pending']);
    }

    public function stockCardCount(Request $request)
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Stock Card per Count report - Implementation pending']);
    }

    public function documentSummary(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Document Summary per Doc report - Implementation pending']);
    }

    public function purchase(Request $request)
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'category' => ['required', 'in:OFFICE SUPPLIES,SPARE PARTS,FACTORY SUPPLIES,CHEMICAL,FUEL,LABEL,CARTON,CAN,RAW MATERIALS,SPICES AND INGREDIENTS,COAL,SLUDGE OIL,LABELING SUPPLIES,MATERIAL IN TRANSIT,FINISHED GOODS,FISH'],
            'format' => ['required', 'in:pdf,excel'],
        ]);

        // Implementation will be added later
        return response()->json(['message' => 'Purchase report - Implementation pending']);
    }
}
