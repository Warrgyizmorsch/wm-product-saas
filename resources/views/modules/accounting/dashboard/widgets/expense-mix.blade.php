<x-ui.card title="Where the Money Went" class="mb-3" stretch>
    @if (count($expenseBreakdown) > 0)
        <div id="acc-expense-chart" style="min-height: 280px;"></div>
    @else
        <div class="text-center py-5 text-muted"><i class="feather-pie-chart fs-1 mb-2 d-block"></i>No expenses in this period.</div>
    @endif
</x-ui.card>
