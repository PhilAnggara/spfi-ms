<?php

use Illuminate\Pagination\LengthAwarePaginator;

it('renders custom bootstrap pagination with mobile page info and list theme classes', function () {
    $paginator = new LengthAwarePaginator(
        collect(range(1, 10)),
        50,
        10,
        2,
        ['path' => 'http://localhost/test'],
    );

    $html = $paginator->links('pagination::bootstrap-5')->toHtml();

    expect($html)
        ->toContain('list-pagination')
        ->toContain('list-pagination-mobile-info')
        ->toContain('Page')
        ->toContain('of')
        ->toContain('Showing')
        ->toContain('gap-3');
});
