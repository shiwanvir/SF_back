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
              <td class="h2">
                Consumption Added to BOM
              </td>
            </tr>
            <tr>
              <td class="bodycopy">
                <p> Dear {{ $user_name }},
                  <br>
                  <br>
                      The Consumption has been added to all Main Materials for Style No - {{ $bom->style_no }} by {{ $cad_user }}.
                  </p>

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
                            <a href="{{config('surface.surface_path')}}/merchandising/bom">Click Here </a><br>
                            <a href="{{config('surface.surface_path')}}/merchandising/bom" style="font-size:9px;">(To Apply Consumption)</a>
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
