<?php

namespace App\Exports\TransferItem;

use App\Model\Inventory\TransferItem\TransferItemCustomer;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Carbon\Carbon;

class TransferItemCustomerSendExport implements WithColumnFormatting, FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    /**
     * ScaleWeightItemExport constructor.
     *
     * @param string $dateFrom
     * @param string $dateTo
     */
    public function __construct(string $dateFrom, string $dateTo, array $ids, string $tenantName)
    {
        $this->dateFrom = date('d F Y', strtotime($dateFrom));
        $this->dateTo = date('d F Y', strtotime($dateTo));
        $this->ids = $ids;
        $this->tenantName = $tenantName;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        $transferItemCustomers = TransferItemCustomer::join('forms', 'forms.formable_id', '=', TransferItemCustomer::getTableName().'.id')
            ->where('forms.formable_type', TransferItemCustomer::$morphName)
            ->whereIn(TransferItemCustomer::getTableName().'.id', $this->ids)
            ->join('warehouses as w1', 'w1.id', '=', TransferItemCustomer::getTableName().'.warehouse_id')
            ->join('users as u1', 'u1.id', '=', 'forms.created_by')
            ->join('customers as c', 'c.id', '=', TransferItemCustomer::getTableName().'.customer_id')
            ->join('expeditions as e', 'e.id', '=', TransferItemCustomer::getTableName().'.expedition_id')
            ->join('transfer_item_customer_items as tii', 'tii.transfer_item_customer_id', '=', TransferItemCustomer::getTableName().'.id')
            ->select('date', 'number', 'w1.name as warehouse', 'c.name as customer', 'e.name as expedition')
            ->addSelect('item_name', 'unit', 'production_number', 'expiry_date', 'quantity', 'balance')
            ->addSelect('u1.name as created_by')
            ->orderBy('number', 'desc');
        return $transferItemCustomers;
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            ['Date Export', ': ' . date('d F Y', strtotime(Carbon::now()))],
            ['Period Export', ': ' . $this->dateFrom . ' - ' . $this->dateTo],
            [$this->tenantName],
            ['Transfer Item Send'],
            [
            'Date Form',
            'Form Number',
            'Warehouse',
            'Customer',
            'Driver',
            'Item',
            'Production Number',
            'Expiry Date',
            'Quantity Send',
            'Created By'
            ]
        ];
    }

    /**
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            date('d F Y', strtotime($row->date)),
            $row->number,
            $row->warehouse,
            $row->customer,
            $row->expedition,
            $row->item_name,
            $row->production_number,
            date('d F Y', strtotime($row->expiry_date)),
            (int)$row->quantity . ' ' . $row->unit,
            $row->created_by,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $event->sheet->getDelegate()->getStyle('F6:F100')
                            ->getAlignment()
                            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $event->sheet->getColumnDimension('B')
                            ->setAutoSize(false)
                            ->setWidth(18);
                $tenanName = 'A3:J3'; // All headers
                $event->sheet->mergeCells($tenanName);
                $event->sheet->getDelegate()->getStyle($tenanName)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($tenanName)
                                ->getAlignment()
                                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $title = 'A4:J4'; // All headers
                $event->sheet->mergeCells($title);
                $event->sheet->getDelegate()->getStyle($title)->getFont()->setBold(true);
                $event->sheet->getDelegate()->getStyle($title)
                                ->getAlignment()
                                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            },

        ];
    }     
}
