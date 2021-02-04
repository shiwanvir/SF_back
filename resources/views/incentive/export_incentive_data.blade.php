<!-- View stored in resources/views/greeting.blade.php -->
<html>
<head>

</head>
<link href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css" rel="stylesheet" type="text/css">
<link href="https://cdn.datatables.net/buttons/1.6.5/css/buttons.dataTables.min.css" rel="stylesheet" type="text/css">









<!-- <script type="text/javascript">

  function exportTableToExcel(tableID, filename = 'Incentive Payment Summary Report'){
    var downloadLink;
    var dataType = 'application/vnd.ms-excel';
    var tableSelect = document.getElementById(tableID);
    var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');


    filename = filename?filename+'.xls':'excel_data.xls';


    downloadLink = document.createElement("a");

    document.body.appendChild(downloadLink);

    if(navigator.msSaveOrOpenBlob){
        var blob = new Blob(['\ufeff', tableHTML], {
            type: dataType
        });
        navigator.msSaveOrOpenBlob( blob, filename);
    }else{

        downloadLink.href = 'data:' + dataType + ', ' + tableHTML;

        downloadLink.download = filename;


        downloadLink.click();
    }
}
</script> -->
<style>
table {
  font-family: arial, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

td, th {
  border: 2px solid #dddddd;
  text-align: center;
  padding: 8px;
}

tr:nth-child(even) {
  background-color: #dddddd;
}
</style>
</head>
    <body>
      <div class="container">
        <!-- <button onclick="exportTableToExcel('tblData', 'members-data')">Export Table Data To Excel File</button> -->
      <div id="table_div">






<!-- <table id="tblData" class="display" style="width:100%">
    <tr>
        <th colspan={{$count_dates+6}} scope="colgroup">INCENTIVE PAYMENT SUMMARY REPORT</th>
    </tr>
      <tr>
          <th colspan="5"></th>
          <th colspan={{$count_dates}}>{{$month->month}}</th>
          <th></th>
      </tr>
      <tr>
        <th>Emp. No</th>
        <th>Name</th>
        <th>Team</th>
        <th>Designation</th>
        <th>Department</th>
        @foreach ($dates as $key=>$row)
          <th>{{$row->dates}}</th>
        @endforeach
        </ng-container>
        <th>Total</th>
        </tr>
        <?php $tot = 0; ?>
        @foreach ($user_list as $key=>$row2)
          <tr>
            <td>{{$row2->emp_no}}</td>
            <td>{{$row2->emp_name}}</td>
            <td>{{$row2->line_no}}</td>
            <td>{{$row2->emp_designation}}</td>
            <td>{{$row2->department}}</td>
                @foreach ($dates as $row)
                <?php $a=0; ?>
                @foreach ($date_wise_user_list as $row5)
                @if($row5->emp_no == $row2->emp_no && $row5->date == $row->dates )
                <?php $a++; ?>
                <td>{{$row5->total}}</td>
                @endif


                @endforeach
                <?php if($a==0){ ?>
                  <td><?php echo $a; ?></td>
                <?php } ?>


                @endforeach
            <td>{{$row2->total}}</td>

          </tr>
          <?php $tot += $row2->total; ?>
          @endforeach

          <tr>
            <th colspan="5"></th>
            @foreach ($dates as $key=>$row)
              <th></th>
            @endforeach
            </ng-container>
            <th>{{ $tot }}</th>
            </tr>

      </table> -->




      <table id="tblData" class="display" style="width:100%">
        <thead style="text-align: center;">
          <tr>
            <th colspan="5"></th>
            <th colspan={{$count_dates}}>{{$month->month}}</th>
            <th></th>
          </tr>

          <tr>
            <th>Emp. No</th>
            <th>Name</th>
            <th>Team</th>
            <th>Designation</th>
            <th>Department</th>
            @foreach ($dates as $key=>$row)
              <th>{{$row->dates}}</th>
            @endforeach
            <th>Total</th>

          </tr>
        </thead>
        <tbody>
          <?php $tot = 0; ?>
          @foreach ($user_list as $key=>$row2)
            <tr>
              <td>{{$row2->emp_no}}</td>
              <td>{{$row2->emp_name}}</td>
              <td>{{$row2->line_no}}</td>
              <td>{{$row2->emp_designation}}</td>
              <td>{{$row2->department}}</td>
                  @foreach ($dates as $row)
                  <?php $a=0; ?>
                  @foreach ($date_wise_user_list as $row5)
                  @if($row5->emp_no == $row2->emp_no && $row5->date == $row->dates )
                  <?php $a++; ?>
                  <td>{{$row5->total}}</td>
                  @endif


                  @endforeach
                  <?php if($a==0){ ?>
                    <td><?php echo '0.00'; ?></td>
                  <?php } ?>


                  @endforeach
              <td>{{$row2->total}}</td>

            </tr>
            <?php $tot += $row2->total; ?>
            @endforeach


        </tbody>
        <tfoot>
          <tr>
            <th colspan="4"> </th>
            <th>Full Month Total </th>
            @foreach ($dates as $key=>$row)
              <th></th>
            @endforeach
            </ng-container>
            <th>{{ $tot }}</th>
            </tr>
        </tfoot>

    </table>



    </div>
      </div>

        </body>

        <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
        <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.flash.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.print.min.js"></script>



        <script type="text/javascript">
            $(document).ready(function() {
            $('#tblData').DataTable( {
                dom: 'Bfrtip',
                buttons: [{
                            extend: 'copyHtml5',
                            text: 'COPY',
                            filename: 'Incentive Payment Summary Report',
                            footer: true,
                            title: 'Incentive Payment Summary Report',
                            },
                            {
                            extend: 'excelHtml5',
                            text: 'EXCEL',
                            filename: 'Incentive Payment Summary Report',
                            customize: function( xlsx ) {
                              var source = xlsx.xl['workbook.xml'].getElementsByTagName('sheet')[0];
                              var sheet = xlsx.xl.worksheets['sheet1.xml'];
                              source.setAttribute('name','<?php echo $month->month; ?>');
                              $('row:eq(0) c', sheet).attr('s','51');
                              //$('row:eq(0) c', sheet).attr('s','30','51');
                              $('row:eq(1) c', sheet).attr('s','51');
                              //$('row:eq(1) c', sheet).attr('s','47','51');

                						},
                            footer: true,
                            title: 'Incentive Payment Summary Report',
                            },
                            {
                            extend: 'pdfHtml5',
                            text: 'PDF',
                            filename: 'Incentive Payment Summary Report',
                            footer: true,
                            title: 'Incentive Payment Summary Report',
              }]
            } );
        } );

        </script>
</html>
