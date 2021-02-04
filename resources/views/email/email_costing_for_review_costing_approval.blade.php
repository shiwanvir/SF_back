<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>A Simple Responsive HTML Email</title>
  <style type="text/css">
  body {margin: 0; padding: 0; min-width: 100%!important;}
  img {height: auto;}
  .content {width: 100%; max-width: 600px;}
  .header {padding: 40px 30px 20px 30px;}
  .innerpadding {padding: 30px 30px 30px 30px;}
  .borderbottom {border-bottom: 1px solid #f2eeed;}
  .subhead {font-size: 15px; color: #ffffff; font-family: sans-serif; letter-spacing: 10px;}
  .h1, .h2, .bodycopy {color: #153643; font-family: sans-serif;}
  .h1 {font-size: 33px; line-height: 38px; font-weight: bold;}
  .h2 {padding: 0 0 15px 0; font-size: 24px; line-height: 28px; font-weight: bold;}
  .h6 {padding: 0 0 15px 0; font-size: 12px; line-height: 28px; font-weight: bold; font color:white;}
  .bodycopy {font-size: 16px; line-height: 22px;}
  .button {text-align: center; font-size: 18px; font-family: sans-serif; font-weight: bold; padding: 0 30px 0 30px;}
  .button a {color: #ffffff; text-decoration: none;}
  .button ab {text-align: center; font-size: 9px; font-family: sans-serif;color: #ffffff;}

  .footer {padding: 20px 30px 15px 30px;}
  .footercopy {font-family: sans-serif; font-size: 14px; color: #ffffff;}
  .footercopy a {color: #ffffff; text-decoration: underline;}

  @media only screen and (max-width: 550px), screen and (max-device-width: 550px) {
  body[yahoo] .hide {display: none!important;}
  body[yahoo] .buttonwrapper {background-color: transparent!important;}
  body[yahoo] .button {padding: 0px!important;}
  body[yahoo] .button a {background-color: #e05443; padding: 15px 15px 13px!important;}
  body[yahoo] .unsubscribe {display: block; margin-top: 20px; padding: 10px 50px; background: #2f3942; border-radius: 5px; text-decoration: none!important; font-weight: bold;}
  }

  /*@media only screen and (min-device-width: 601px) {
    .content {width: 600px !important;}
    .col425 {width: 425px!important;}
    .col380 {width: 380px!important;}
    }*/

  </style>
</head>

<body yahoo bgcolor="#E2E2E2">
<table width="100%" bgcolor="#E2E2E2" border="0" cellpadding="0" cellspacing="0">
<tr>
  <td>
    <!--[if (gte mso 9)|(IE)]>
      <table width="600" align="center" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td>
    <![endif]-->
    <table bgcolor="#ffffff" class="content" align="center" cellpadding="0" cellspacing="0" border="0">
      <tr>
        <td bgcolor="#37474f" class="header">
          <table width="70" align="left" border="0" cellpadding="0" cellspacing="0">
            <tr>
              <td height="30" >
                <img class="fix" src="http://test-surface.helaclothing.com/test/surfacedev/resources/images/logo_light.png" width="150" border="0" alt="" /><br>
              </td>
            </tr>
          </table>

        </td>
      </tr>
      <tr>
        <td class="innerpadding borderbottom">
          <table width="100%" border="0" cellspacing="0" cellpadding="0">

            <tr>
              <td class="h2"> <u>
                Costing for Review </u>
              </td>
            </tr>
            <tr>
              <td class="bodycopy">
                <p> Dear {{ $mail_user_login->user_name }},
                  <br>
                  <br>
                  Please find below the {{ $costing->bom_stage_id }} costing for style - {{ $costing->style_id }}, of {{ $customer->customer_name }}, for your approval, sent by {{ $merchant->user_name }}   <br>
                  <br>
                  <b>Please Review:</b>

                </p>
              <table class="table table-borderless" width="100%" border="0" cellspacing="0" cellpadding="0">

      					  <tr>
      					    <th width="24%" align="left">Costing EPM</th>
      					    <th width="2%">:</th>
      					    <td width="24%">{{$costing->epm}}</td>
                  </tr>
                  <tr>
                    <th width="40%" align="left">Costing NP Margin</th>
                    <th width="2%">:</th>
                    <td width="24%">{{$costing->np_margine}}</td>
                  </tr>
                  <tr>
      					    <th width="24%" align="left">SMV</th>
      					    <th width="2%">:</th>
      					    <td width="24%" colspan="4">{{ $costing->total_smv }}</td>
      					  </tr>
                  <tr>
                    <th width="24%" align="left">FOB</th>
                    <th width="2%">:</th>
                    <td width="24%" colspan="4">$ {{ $costing->fob }}</td>
                  </tr>


                </table>
                <br>

                <table class="table table-striped" width="100%" style="text-align:center" border="1">
        				  <thead>
        				    <tr>
        				      <th>Breakdown</th>
        				      <th>Cost ($)</th>
                      <th>Cost Percentage %</th>
                    </tr>
        				  </thead>
        				  <tbody>
        				    <tr>
        				      <td>Fabric</td>
                      <td>{{ $costing->fabric_cost }}</td>
                      <td><?= round((($costing->fabric_cost / $costing->total_rm_cost) * 100), 2, PHP_ROUND_HALF_UP ) ?></td>
              	    </tr>
                    <tr>
        				      <td>Sewing Trims</td>
                      <td>{{ $costing->trim_cost }}</td>
                      <td><?= round((($costing->trim_cost / $costing->total_rm_cost) * 100), 2, PHP_ROUND_HALF_UP ) ?></td>
              	    </tr>
                    <tr>
        				      <td>Packing Trims</td>
                      <td>{{ $costing->packing_cost }}</td>
                      <td><?= round((($costing->packing_cost / $costing->total_rm_cost) * 100), 2, PHP_ROUND_HALF_UP ) ?></td>
              	    </tr>
                    <tr>
        				      <td>Services</td>
                      <td>{{ $costing->other_cost }}</td>
                      <td><?= round((($costing->other_cost / $costing->total_rm_cost) * 100), 2, PHP_ROUND_HALF_UP ) ?></td>
              	    </tr>
                    <tr>
        				      <td>Total</td>
                      <td>{{ $costing->total_rm_cost }}</td>
                      <td><?= round((($costing->total_rm_cost / $costing->total_rm_cost) * 100), 2, PHP_ROUND_HALF_UP ) ?></td>
              	    </tr>
        				  </tbody>
        				</table>

                <p> Please reply with letter A to approve, R to reject, or approve on the system; </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
      <tr>
        <td class="innerpadding borderbottom">

          <table class="col380" align="left" border="0" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 380px;">
            <tr>
              <td>
                <table width="100%" border="0" cellspacing="0" cellpadding="0">
                  <!-- <tr>
                    <td class="bodycopy">

                      This is an automatically generated email message from Surface&trade;.

                      </td>
                  </tr> -->
                  <tr>
                    <td style="padding: 10px 0 0 0;">
                      <table class="buttonwrapper" bgcolor="#e05443" border="0" cellspacing="0" cellpadding="0">
                        <tr>
                          <td class="button" height="45">
                            <a href="#">Click Here </a><br>
                            <ab>[ To View Detailed Costing  ]</ab>
                          </td>
                        </tr>
                      </table>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td class="innerpadding borderbottom">
            <table class="table table-borderless" width="100%" border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td>
                  <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                      <td class="bodycopy">

                        @if ($approval_details != null)
                          $level_no = 1;
                          $level_letter = '';
                          @foreach ($approval_details as $row)
                          $level_no = $row->stage_order + 1;
                          switch ($level_no % 10) {
                            // Handle 1st, 2nd, 3rd
                            case 1:  $level_letter = 'st'; break;
                            case 2:  $level_letter = 'nd';break;
                            case 3:  $level_letter = 'rd'; break;
                            default : $level_letter = 'th'; break;
                          }
                          <tr>
                            <th width="15%" align="left">{{ $level_no }}<sup>{{ $level_letter }}</sup> approval by</th>
                            <th width="2%">:</th>
                            <td width="24%">{{ $row->user_name }}</td>

                            <th width="15%" align="left">Date & Time</th>
                            <th width="2%">:</th>
                            <td width="15%">{{ date("F jS, Y g:i a", strtotime($row->approval_date)) }}</td>
                          </tr>

                          @endforeach

                          switch ($level_no % 10) {
                            // Handle 1st, 2nd, 3rd
                            case 1:  $level_letter = 'st'; break;
                            case 2:  $level_letter = 'nd';break;
                            case 3:  $level_letter = 'rd'; break;
                            default : $level_letter = 'th'; break;
                          }
                          <tr>
                            <th width="15%" align="left">{{ $level_no }}<sup>{{ $level_letter }}</sup> approval by</th>
                            <th width="2%">:</th>
                            <td width="24%">Pending</td>

                            <th width="15%" align="left">Date & Time</th>
                            <th width="2%">:</th>
                            <td width="15%">Pending</td>
                          </tr>

                        @else
                          <tr>
                            <th width="15%" align="left">1<sup>st</sup> approval by</th>
                            <th width="2%">:</th>
                            <td width="24%">Pending</td>

                            <th width="15%" align="left">Date & Time</th>
                            <th width="2%">:</th>
                            <td width="15%">Pending</td>
                          </tr>
                        @endif

                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
        </td>
      </tr>

      <tr>
       <td class="bodycopy">
           <center> <label style="font-size:10px;">This is an automatically generated email message from Surface&trade;.</label></center>
       </td>
      </tr>
      <tr>
        <td class="footer" bgcolor="#37474f">
          <table width="100%" border="0" cellspacing="0" cellpadding="0">
            <tr>
              <td align="center" class="footercopy">
                &reg; HELA CLOTHING PVT LTD, 2019<br/>

              </td>
            </tr>
            <tr>
              <td align="center" style="padding: 20px 0 0 0;">
                <table border="0" cellspacing="0" cellpadding="0">
                  <tr>
                  </tr>
                </table>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
    <!--[if (gte mso 9)|(IE)]>
          </td>
        </tr>
    </table>
    <![endif]-->
    </td>
  </tr>
</table>
</body>
</html>
