<!-- View stored in resources/views/greeting.blade.php -->
<html>
<head>

</head>
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
    <body onload="downloadExcel()">
      <div class="container">
      <input type="hidden"  id="silhouete" name="" value="{{$header['product_silhouette_description']}}">
      <div id="table_div">

        <table>
          <tr>
              <th style="border: 1px solid black; background-color:#0000FF;color:#FFFFFF" colspan="18" scope="colgroup">OPERATIONS BREAKDOWN SHEET</th>
          </tr>
          </table>
      <br>
      <h3>Print Ref#{{$header['smv_reading_id']}}</h3>
      <h3>version#{{$header['version']}}</h3>
      <br>

      <table id="header" style="width:400px">
        <tr>
          <td style="border: 1px solid black;">Style NO</td>
          <td style="border: 1px solid black"></td>
        </tr>
        <tr>
          <td style="border: 1px solid black">SC NO</td>
          <td style="border: 1px solid black"></td>
        </tr>
        <tr>
          <td style="border: 1px solid black">Customer</td>
          <td style="border: 1px solid black"></td>
        </tr>
        <tr>
          <td style="border: 1px solid black">IE Name</td>
          <td style="border: 1px solid black"></td>
        </tr>
        <tr>
          <td style="border: 1px solid black">Item</td>
          <td style="border: 1px solid black"></td>
        </tr>
        <tr>
          <td style="border: 1px solid black">Sample Date</td>
          <td style="border: 1px solid black"></td>
        </tr>
        <tr>
          <td style="border: 1px solid black">Date</td>
          <td style="border: 1px solid black"></td>
        </tr>
      </table>
      <br>
      <h3>NOTE</h3>
      <br>
      <table id="details">
          <tr>
        <th></th>
        <th></th>
        <th></th>
        <th></th>
        <th style="border: 1px solid black; background-color:#BBE3E6" colspan="3" scope="colgroup">Needles</th>
        <th style="border: 1px solid black; background-color:#BBE3E6" colspan="4" scope="colgroup">Sewing</th>
        <th style="border: 1px solid black; background-color:#BBE3E6" colspan="3" scope="colgroup">Threads</th>
        <th style="border: 1px solid black; background-color:#BBE3E6" colspan="3" scope="colgroup">Folder</th>
        <th ></th>
        </tr>
        <tr>
        <th style="border: 1px solid black; background-color:#BBE3E6">NO</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">OPERATION</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">M/C Type</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">S.M.V</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Needle Gauge</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Spreader</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Needle Type</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Throw</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Seam Allowances</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Trim Allowances</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">SPI SPCM</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Threads Needle</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Threads Loopers/Bobbing</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Threads Spreaders</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Folder Type</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Finish Width Raw/Cover</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Cuttable Width</th>
        <th style="border: 1px solid black; background-color:#BBE3E6">Other Comments</th>
        </tr>
          @foreach ($details as $key=>$row)
          <tr>
          <td style="border: 1px solid black">{{$key+1}}</td>
          <td style="border: 1px solid black">{{ $row->operation_name }}</td>
          <td style="border: 1px solid black">{{ $row->machine_type_id }}</td>
          <td style="border: 1px solid black">{{ $row->cost_smv }}</td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          <td style="border: 1px solid black"></td>
          </tr>
          @endforeach

      </table>

    </div>
      </div>
      <script type="text/javascript">
        function downloadExcel(){
        // debugger
          var table="table_div";
          var name= document.getElementById("silhouete").value;
          var filename="Operation Data Sheet.xls";
          let uri = 'data:application/vnd.ms-excel;base64,',
          template = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><title></title><head><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>{worksheet}</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--><meta http-equiv="content-type" content="text/plain; charset=UTF-8"/></head><body><table>{table}</table></body></html>',
          base64 = function(s) { return window.btoa(decodeURIComponent(encodeURIComponent(s))) },         format = function(s, c) { return s.replace(/{(\w+)}/g, function(m, p) { return c[p]; })}

          if (!table.nodeType){
            table = document.getElementById(table)
            }
          var ctx = {worksheet: name || 'Worksheet', table: table.innerHTML


                   }
    //debugger
          var link = document.createElement('a');
          link.download = filename;
          link.href = uri + base64(format(template, ctx));
          link.click();
        }
      </script>
        </body>
</html>
