<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ProductSampleExport implements FromCollection, WithHeadings, WithEvents
{
    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Item Name',
            'SKU',
            'Variation Type',
            'Parent SKU',
            'Variant Attributes',
            'Type',
            'Item Type',
            'Supplier Method',
            'Unit / UOM',
            'Selling Price',
            'Cost Price',
            'Opening Stock',
            'Warehouse',
            'Reorder Point',
            'HSN/SAC',
            'GST Rate (%)',
            'Valuation Method',
            'Length',
            'Width',
            'Height',
            'Dimension Unit',
            'Weight',
            'Weight Unit',
            'Description',
            'Status'
        ];
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return collect([
            [
                'Dining Chair Teak DC-1',
                'FG-CHAIR-DC1',
                'Single',
                '',
                '',
                'finished_good',
                'Goods',
                'manufacture',
                'PCS',
                '4500.00',
                '3200.00',
                '25.00',
                'Main Warehouse',
                '5.00',
                '94036000',
                '18.00',
                'FIFO',
                '50.00',
                '45.00',
                '90.00',
                'cm',
                '7.50',
                'kg',
                'Solid teak wood dining chair with natural finish',
                'active'
            ],
            [
                'Classic Cotton T-Shirt',
                'TSHIRT-PARENT',
                'Variant',
                '',
                'Color: Red, Blue | Size: M, L',
                'finished_good',
                'Goods',
                'manufacture',
                'Pieces',
                '200.00',
                '100.00',
                '-',
                'Main Warehouse',
                '10.00',
                '61091000',
                '18.00',
                'FIFO',
                '30.00',
                '25.00',
                '2.00',
                'cm',
                '0.20',
                'kg',
                'Premium 100% cotton crewneck t-shirt with variant options',
                'active'
            ],
            [
                'Classic Cotton T-Shirt (Color: Red, Size: M)',
                'TSHIRT-RED-M',
                'Single',
                'TSHIRT-PARENT',
                'Color: Red | Size: M',
                'finished_good',
                'Goods',
                'manufacture',
                'Pieces',
                '200.00',
                '100.00',
                '50.00',
                'Main Warehouse',
                '5.00',
                '61091000',
                '18.00',
                'FIFO',
                '30.00',
                '25.00',
                '2.00',
                'cm',
                '0.20',
                'kg',
                'Red color medium size variant t-shirt',
                'active'
            ],
            [
                'Teak Wood Board 2x4',
                'RM-TEAK-2X4',
                'Single',
                '',
                '',
                'raw_material',
                'Goods',
                'trade',
                'MTR',
                '800.00',
                '550.00',
                '150.00',
                'Main Warehouse',
                '20.00',
                '44071000',
                '18.00',
                'Weighted Average',
                '240.00',
                '10.00',
                '5.00',
                'cm',
                '3.20',
                'kg',
                'Seasoned raw teak wood board for furniture manufacturing',
                'active'
            ]
        ]);
    }

    /**
     * @return array
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Variation Type validation (Column C)
                $varList = '"Single,Variant"';
                $validationVarType = $sheet->getCell('C2')->getDataValidation();
                $validationVarType->setType(DataValidation::TYPE_LIST);
                $validationVarType->setErrorStyle(DataValidation::STYLE_STOP);
                $validationVarType->setAllowBlank(true);
                $validationVarType->setShowDropDown(true);
                $validationVarType->setFormula1($varList);

                // Types list validation (Column F)
                $typesList = '"finished_good,raw_material,component,service,consumable"';
                $validationType = $sheet->getCell('F2')->getDataValidation();
                $validationType->setType(DataValidation::TYPE_LIST);
                $validationType->setErrorStyle(DataValidation::STYLE_STOP);
                $validationType->setAllowBlank(true);
                $validationType->setShowDropDown(true);
                $validationType->setFormula1($typesList);

                // Item Type validation (Column G)
                $itemTypesList = '"Goods,Service"';
                $validationItemType = $sheet->getCell('G2')->getDataValidation();
                $validationItemType->setType(DataValidation::TYPE_LIST);
                $validationItemType->setErrorStyle(DataValidation::STYLE_STOP);
                $validationItemType->setAllowBlank(true);
                $validationItemType->setShowDropDown(true);
                $validationItemType->setFormula1($itemTypesList);

                // Supplier Method validation (Column H)
                $supplierMethodsList = '"trade,manufacture"';
                $validationSupplierMethod = $sheet->getCell('H2')->getDataValidation();
                $validationSupplierMethod->setType(DataValidation::TYPE_LIST);
                $validationSupplierMethod->setErrorStyle(DataValidation::STYLE_STOP);
                $validationSupplierMethod->setAllowBlank(true);
                $validationSupplierMethod->setShowDropDown(true);
                $validationSupplierMethod->setFormula1($supplierMethodsList);

                // Valuation Method validation (Column Q)
                $valuationList = '"FIFO,Weighted Average"';
                $validationValuation = $sheet->getCell('Q2')->getDataValidation();
                $validationValuation->setType(DataValidation::TYPE_LIST);
                $validationValuation->setErrorStyle(DataValidation::STYLE_STOP);
                $validationValuation->setAllowBlank(true);
                $validationValuation->setShowDropDown(true);
                $validationValuation->setFormula1($valuationList);

                // Dimension Unit validation (Column U)
                $dimUnitsList = '"cm,in,mm,m"';
                $validationDimUnit = $sheet->getCell('U2')->getDataValidation();
                $validationDimUnit->setType(DataValidation::TYPE_LIST);
                $validationDimUnit->setErrorStyle(DataValidation::STYLE_STOP);
                $validationDimUnit->setAllowBlank(true);
                $validationDimUnit->setShowDropDown(true);
                $validationDimUnit->setFormula1($dimUnitsList);

                // Weight Unit validation (Column W)
                $weightUnitsList = '"kg,g,lb,oz"';
                $validationWeightUnit = $sheet->getCell('W2')->getDataValidation();
                $validationWeightUnit->setType(DataValidation::TYPE_LIST);
                $validationWeightUnit->setErrorStyle(DataValidation::STYLE_STOP);
                $validationWeightUnit->setAllowBlank(true);
                $validationWeightUnit->setShowDropDown(true);
                $validationWeightUnit->setFormula1($weightUnitsList);

                for ($i = 3; $i <= 100; $i++) {
                    $sheet->getCell("C{$i}")->setDataValidation(clone $validationVarType);
                    $sheet->getCell("F{$i}")->setDataValidation(clone $validationType);
                    $sheet->getCell("G{$i}")->setDataValidation(clone $validationItemType);
                    $sheet->getCell("H{$i}")->setDataValidation(clone $validationSupplierMethod);
                    $sheet->getCell("Q{$i}")->setDataValidation(clone $validationValuation);
                    $sheet->getCell("U{$i}")->setDataValidation(clone $validationDimUnit);
                    $sheet->getCell("W{$i}")->setDataValidation(clone $validationWeightUnit);
                }
            }
        ];
    }
}
