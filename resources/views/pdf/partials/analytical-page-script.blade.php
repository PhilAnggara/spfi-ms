<script type="text/php">
    if (isset($pdf)) {
        $pdf->page_script('
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            if (! $font) {
                $font = $fontMetrics->getFont("helvetica", "normal");
            }

            $fontSize = 7;
            $rightEdge = $pdf->get_width() - 28.35;

            $pageLabel = "Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $pageLabelWidth = $fontMetrics->get_text_width($pageLabel, $font, $fontSize);
            $pdf->text($rightEdge - $pageLabelWidth, 24, $pageLabel, $font, $fontSize, array(0.2, 0.2, 0.2));

            $printedLabel = "Printed: " . {!! json_encode($printed_at) !!};
            $printedLabelWidth = $fontMetrics->get_text_width($printedLabel, $font, $fontSize);
            $pdf->text($rightEdge - $printedLabelWidth, 34, $printedLabel, $font, $fontSize, array(0.2, 0.2, 0.2));
        ');
    }
</script>
