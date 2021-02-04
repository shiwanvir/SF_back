<?php

namespace App\Exports;

use App\Models\Org\UOM;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use App\Models\IE\SMVReadingHeader;
use App\Models\IE\SMVReadingDetails;
use App\Models\IE\SMVReadingSummary;
use DB;

class OperationBreakdownDownload implements  FromArray, WithHeadings,WithStrictNullComparison,ShouldAutoSize,WithEvents
{
    use Exportable;

    function __construct($id) {

         $this->id = $id;
      //   dd($this->id);
  }

  public function array(): array
  {
    //$records= new \stdClass();
    $smvreadingheader=DB::SELECT("SELECT ie_smv_reading_header.smv_reading_id,ie_smv_reading_header.version,
      cust_customer.customer_id,product_silhouette.product_silhouette_id,ie_smv_reading_header.total_smv
        FROM
        ie_smv_reading_header
        INNER JOIN ie_smv_reading_details on ie_smv_reading_header.smv_reading_id=ie_smv_reading_details.smv_reading_id
        INNER JOIN ie_operation_component ON ie_smv_reading_details.operation_component_id = ie_operation_component.operation_component_id
        INNER JOIN cust_customer ON ie_smv_reading_header.customer_id = cust_customer.customer_id
        INNER JOIN product_silhouette ON ie_smv_reading_header.product_silhouette_id = product_silhouette.product_silhouette_id
        WHERE
        ie_smv_reading_header.smv_reading_id =$this->id");

        $smvreadingheader2=DB::SELECT("SELECT ie_smv_reading_header.smv_reading_id,ie_smv_reading_header.version,
          cust_customer.customer_id,product_silhouette.product_silhouette_id,ie_smv_reading_header.total_smv
            FROM
            ie_smv_reading_header
            INNER JOIN ie_smv_reading_details on ie_smv_reading_header.smv_reading_id=ie_smv_reading_details.smv_reading_id
            INNER JOIN ie_operation_component ON ie_smv_reading_details.operation_component_id = ie_operation_component.operation_component_id
            INNER JOIN cust_customer ON ie_smv_reading_header.customer_id = cust_customer.customer_id
            INNER JOIN product_silhouette ON ie_smv_reading_header.product_silhouette_id = product_silhouette.product_silhouette_id
            WHERE
            ie_smv_reading_header.smv_reading_id =$this->id");
    //$records->h1=$smvreadingheader;
  //  $records->h2=$smvreadingheader2;
  /*  $records = array(
    "h1" => $smvreadingheader,
    "h2" => $smvreadingheader2,
  );*/
    //  dd($smvreadingheader);
      return  [
        [$smvreadingheader],
        [$smvreadingheader2]
      ];
  }

  public function map($ar): array
  {
    //dd($ar);

      return [
        [
        $ar->h1->smv_reading_id,
        $ar->h1->version,
        $ar->h1->customer_id,
        $ar->h1->product_silhouette_id,
        $ar->h1->total_smv
      ],
      [
      $ar->h2->smv_reading_id,
      $ar->h2->version,
      $ar->h2->customer_id,
      $ar->h2->product_silhouette_id,
      $ar->h2->total_smv
    ],

      ];
  }

  public function registerEvents(): array
   {
     //dd("sasasa");
       return [
           AfterSheet::class=> function(AfterSheet $event) {
            // dd($event);
               $cellRange = 'A1:E1'; // All headers
               //$event->sheet->getStyle('B1')->getFont()->setBold(true);
               $event->sheet->getDelegate()->getStyle($cellRange)->applyFromArray([
                        'borders' => [
                            'outline' => [
                                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                'color' => ['argb' => 'EB2727'],
                            ],
                        ]
    ]);


             },

       ];
   }


  public function headings(): array
  {
    //dd("dad");
      return [
        [
          '#',
          'Version',
          'Customer',
          'Silhouette',
          'Total SMV'
        ],
        [
          'abc',
          'Version2',
          'Customer3',
          'Silhouette3',
          'Total SMV3'
          ]
      ];
  }




}
