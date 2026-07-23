<?php
/**
 * Settings storage.
 *
 * @package RELODReferralPoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RRP_Settings {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	protected $option_key = 'rrp_settings';

	/**
	 * Default full HTML body for points-expiring email.
	 *
	 * @return string
	 */
	protected static function points_expiring_email_body_default() {
		return <<<'HTML'
<!DOCTYPE html>
<html lang="ru" xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Твои баллы скоро сгорят — amarèssence</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style type="text/css">
    body,
    table,
    td,
    a {
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
    }

    table,
    td {
      mso-table-lspace: 0pt;
      mso-table-rspace: 0pt;
    }

    img {
      -ms-interpolation-mode: bicubic;
      border: 0;
      outline: none;
      text-decoration: none;
    }

    @media only screen and (max-width:620px) {
      .email-outer {
        padding: 0 !important;
      }

      .email-card {
        border-radius: 0 !important;
      }

      .email-pad {
        padding-left: 20px !important;
        padding-right: 20px !important;
      }

      .hdr-pad {
        padding: 22px 20px !important;
      }

      .status-pad {
        padding: 36px 20px 8px !important;
      }

      .ftr-pad {
        padding: 24px 20px !important;
      }

      .support-pad {
        padding: 14px 18px !important;
      }

      .points-card {
        padding: 22px 20px !important;
      }

      .points-big {
        font-size: 52px !important;
      }

      .cta-btn {
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
        text-align: center !important;
        padding-left: 20px !important;
        padding-right: 20px !important;
      }

      /* Catalog: stack two columns on mobile */
      .cat-col {
        display: block !important;
        width: 100% !important;
        padding: 0 0 12px 0 !important;
      }

      .cat-cell {
        display: block !important;
        width: 100% !important;
      }

      .balance-row td {
        display: block !important;
        width: 100% !important;
        text-align: center !important;
        padding: 4px 0 !important;
      }
    }
  </style>
  </head>

<body style="margin:0; padding:0; background-color:#e8e3d8; font-family:Arial, Helvetica, sans-serif;
             -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;">

  <!-- PREHEADER -->
  <div
    style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#e8e3d8;">
    Успейте использовать до {expire_date}&nbsp;— баллы можно применить к любому заказу на
    сайте.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
  </div>

  <!-- OUTER WRAPPER -->
  <table class="email-outer" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
    style="background-color:#e8e3d8; padding:40px 16px;">
    <tr>
      <td align="center" valign="top">

        <!-- EMAIL CARD -->
        <table class="email-card" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
          style="max-width:600px; width:100%; background-color:#f7f3ea; border-radius:4px;">


          <!-- ════ HEADER ════ -->
          <tr>
            <td class="hdr-pad" style="background-color:#662132; padding:24px 40px; text-align:center;">
              <!--[if mso]>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">
              <![endif]-->
              <a href="https://amaressence.ru/" target="_blank" style="display:inline-block; text-decoration:none;">
                <img src="https://eimage.sendsay.ru/image/x_177486673298052/logo%2Dwhite2.png" alt="amarèssence"
                  width="160" style="display:block; width:160px; max-width:160px; height:auto; border:0; outline:none;">
              </a>
              <!--[if mso]></td></tr></table><![endif]-->
            </td>
          </tr>


          <!-- ════ STATUS ICON + HEADING ════ -->
          <tr>
            <td class="status-pad" style="background-color:#f7f3ea; padding:40px 40px 4px; text-align:center;">

              <!-- Hourglass / timer icon -->
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"
                style="margin:0 auto 18px;">
                <tr>
                  <td align="center" valign="middle" style="width:54px; height:54px; background-color:#d0e3ff; border-radius:27px;
                           text-align:center; vertical-align:middle;">
                    <!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                      style="height:54px;v-text-anchor:middle;width:54px;" arcsize="50%"
                      fillcolor="#d0e3ff" strokecolor="#d0e3ff"><w:anchorlock/><center><![endif]-->
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                      style="display:inline-block; vertical-align:middle;">
                      <!-- Hourglass icon -->
                      <path d="M5 22h14" stroke="#662132" stroke-width="1.8" stroke-linecap="round" />
                      <path d="M5 2h14" stroke="#662132" stroke-width="1.8" stroke-linecap="round" />
                      <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"
                        stroke="#662132" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                      <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2" stroke="#662132"
                        stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <!--[if mso]></center></v:roundrect><![endif]-->
                  </td>
                </tr>
              </table>

              <h1 style="margin:0 0 10px; font-family:Georgia, 'Times New Roman', serif; font-size:26px;
                          font-weight:400; color:#662132; letter-spacing:0.02em; line-height:1.3;">
                Твои баллы пока еще с тобой —<br>но ненадолго!:(
              </h1>
            </td>
          </tr>


          <!-- ════ GREETING ════ -->
          <tr>
            <td class="email-pad" style="padding:26px 40px 0;">
              <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px;
                         color:#3d3028; line-height:1.75;">
                {customer_first_name}, хотим напомнить: на твоем счете накоплены бонусные баллы, и срок их действия
                подходит к
                концу. Мы бы не хотели, чтобы они пропали!
              </p>
            </td>
          </tr>


          <!-- ════ DIVIDER ════ -->
          <tr>
            <td class="email-pad" style="padding:24px 40px 0;">
              <div style="height:1px; background-color:rgba(102,33,50,0.12); line-height:1px; font-size:1px;">&nbsp;
              </div>
            </td>
          </tr>


          <!-- ════ POINTS CARD ════ -->
          <tr>
            <td class="email-pad" style="padding:24px 40px 0;">
              <p style="margin:0 0 14px; font-family:Arial, Helvetica, sans-serif; font-size:11px; font-weight:700;
                         letter-spacing:0.14em; color:#662132; text-transform:uppercase; line-height:1;">
                Ваш баланс
              </p>

              <table class="points-card" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background-color:#d0e3ff; border-radius:4px;">
                <tr>
                  <td class="points-card" style="padding:28px 28px 26px; text-align:center;">

                    <!-- Ginkgo leaf -->
                    <p style="margin:0 0 10px; font-size:24px; line-height:1;">🌿</p>

                    <!-- Points big number -->
                    <p class="points-big" style="margin:0 0 2px; font-family:Georgia, 'Times New Roman', serif;
                               font-size:60px; font-weight:400; color:#662132; line-height:1;">
                      {loyalty_balance}
                    </p>
                    <p style="margin:0 0 20px; font-family:Arial, Helvetica, sans-serif; font-size:13px;
                               color:#662132; letter-spacing:0.10em; text-transform:uppercase; font-weight:700;">
                      баллов
                    </p>

                    <!-- Expiry date row -->
                    <table class="balance-row" role="presentation" cellpadding="0" cellspacing="0" border="0"
                      align="center" style="margin:0 auto 22px;">
                      <tr>
                        <td style="font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#5a7090;
                                   padding-right:10px; vertical-align:middle; white-space:nowrap;">
                          Действуют до:
                        </td>
                        <td style="font-family:Georgia, 'Times New Roman', serif; font-size:17px; font-weight:400;
                                   color:#662132; vertical-align:middle; white-space:nowrap;">
                          {expire_date}
                        </td>
                      </tr>
                    </table>

                    <!-- Rule note -->
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                      style="margin-bottom:24px; background-color:rgba(255,255,255,0.55); border-radius:3px;">
                      <tr>
                        <td style="padding:12px 16px; text-align:center;">
                          <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px;
                                     color:#3d3028; line-height:1.65;">
                            Используй баллы для оплаты при оформлении любого заказа на сайте
                            <strong style="white-space:nowrap;">1 балл = 1&nbsp;рубль</strong> скидки.
                          </p>
                        </td>
                      </tr>
                    </table>

                    <!-- CTA -->
                    <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="100%">
                      <tr>
                        <td align="center">
                          <!--[if mso]>
                          <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
                            href="https://amaressence.ru/shop/"
                            style="height:50px;v-text-anchor:middle;width:260px;" arcsize="2%"
                            fillcolor="#662132" strokecolor="#662132">
                            <w:anchorlock/>
                            <center style="color:#f7f3ea;font-family:Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">
                              Использовать баллы
                            </center>
                          </v:roundrect>
                          <![endif]-->
                          <!--[if !mso]><!-->
                          <a class="cta-btn" href="https://amaressence.ru/shop/" target="_blank" style="display:inline-block; background-color:#662132; color:#f7f3ea;
                                   font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:700;
                                   letter-spacing:0.12em; text-decoration:none; text-transform:uppercase;
                                   padding:15px 44px; border-radius:2px; min-width:180px; text-align:center;">
                            Использовать баллы
                          </a>
                          <!--<![endif]-->
                        </td>
                      </tr>
                    </table>

                  </td>
                </tr>
              </table>

              <!-- Link to loyalty rules -->
              <p style="margin:12px 0 0; text-align:center;">
                <a href="https://amaressence.ru/informacziya/#ue-tab2" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:12px; color:#662132;
                         text-decoration:underline; text-underline-offset:2px;">
                  Подробнее о программе лояльности →
                </a>
              </p>
            </td>
          </tr>


          <!-- ════ DIVIDER ════ -->
          <tr>
            <td class="email-pad" style="padding:28px 40px 0;">
              <div style="height:1px; background-color:rgba(102,33,50,0.12); line-height:1px; font-size:1px;">&nbsp;
              </div>
            </td>
          </tr>








          <!-- ════ SUPPORT BLOCK ════ -->
          <tr>
            <td class="email-pad" style="padding:20px 40px 36px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background-color:#ffffff; border-radius:4px; border:1px solid rgba(102,33,50,0.10);">
                <tr>
                  <td class="support-pad" style="padding:18px 22px;">
                    <p style="margin:0 0 4px; font-family:Arial, Helvetica, sans-serif; font-size:13px;
                               font-weight:600; color:#3d3028;">
                      Вопросы о программе лояльности?
                    </p>
                    <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px;
                               color:#9a8880; line-height:1.65;">
                      Напишите нам на
                      <a href="mailto:info@amaressence.ru"
                        style="color:#662132; text-decoration:none; font-weight:600;">
                        info@amaressence.ru
                      </a>
                      &nbsp;·&nbsp;
                      <a href="https://amaressence.ru/informacziya/#ue-tab2" target="_blank"
                        style="color:#662132; text-decoration:underline; text-underline-offset:2px;">
                        Правила программы
                      </a>
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>


          <!-- ════ FOOTER ════ -->
          <tr>
            <td class="ftr-pad" style="background-color:#662132; padding:28px 40px;
                                        text-align:center; border-radius:0 0 4px 4px;">
              <a href="https://amaressence.ru/" target="_blank"
                style="display:inline-block; text-decoration:none; margin-bottom:16px;">
                <img src="https://eimage.sendsay.ru/image/x_177486673298052/logo%2Dwhite2.png" alt="amarèssence"
                  width="120" style="display:block; width:120px; max-width:120px; height:auto;
                            border:0; outline:none; opacity:0.9;">
              </a>

              <p style="margin:0 0 16px; font-size:0; line-height:0;">

                <a href="https://vk.com/amaressence" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:10px; font-weight:600;
                         letter-spacing:0.12em; color:rgba(247,243,234,0.75); text-decoration:none;
                         text-transform:uppercase; margin:0 8px;">VK</a>
                <a href="https://t.me/amaressence" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:10px; font-weight:600;
                         letter-spacing:0.12em; color:rgba(247,243,234,0.75); text-decoration:none;
                         text-transform:uppercase; margin:0 8px;">Telegram</a>
              </p>

              <p style="margin:0 0 6px; font-family:Arial, Helvetica, sans-serif; font-size:11px;
                         color:rgba(247,243,234,0.50); line-height:1.65;">
                Вы получили это письмо как участник программы лояльности amarèssence.
              </p>
              <p style="margin:0 0 10px; font-family:Arial, Helvetica, sans-serif; font-size:11px;
                         color:rgba(247,243,234,0.50); line-height:1.65;">
                <a href="https://amaressence.ru/privacy-policy/" target="_blank"
                  style="color:rgba(247,243,234,0.65); text-decoration:underline;">
                  Политика конфиденциальности
                </a>
                &nbsp;|&nbsp;
                <a href="https://amaressence.ru/informacziya/#ue-tab2" target="_blank"
                  style="color:rgba(247,243,234,0.65); text-decoration:underline;">
                  Программа лояльности
                </a>
              </p>
              <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:10px;
                         color:rgba(247,243,234,0.35); line-height:1.5;">
                © 2026 amarèssence. Все права защищены.
              </p>
            </td>
          </tr>


        </table>
        <!-- /EMAIL CARD -->

      </td>
    </tr>
  </table>

</body>

</html>
HTML;
	}

	/**
	 * Default full HTML body for the first-order referral email.
	 *
	 * Standalone, brand-matched document with fully inline styles so it renders
	 * correctly in Gmail and other clients and never needs the WooCommerce frame.
	 *
	 * @return string
	 */
	protected static function first_order_email_body_default() {
		return <<<'HTML'
<!DOCTYPE html>
<html lang="ru" xmlns="http://www.w3.org/1999/xhtml">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
  <title>Спасибо за заказ — amarèssence</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style type="text/css">
    body,
    table,
    td,
    a {
      -webkit-text-size-adjust: 100%;
      -ms-text-size-adjust: 100%;
    }

    table,
    td {
      mso-table-lspace: 0pt;
      mso-table-rspace: 0pt;
    }

    img {
      -ms-interpolation-mode: bicubic;
      border: 0;
      outline: none;
      text-decoration: none;
    }

    @media only screen and (max-width:620px) {
      .email-outer {
        padding: 0 !important;
      }

      .email-card {
        border-radius: 0 !important;
      }

      .email-pad {
        padding-left: 20px !important;
        padding-right: 20px !important;
      }

      .hdr-pad {
        padding: 22px 20px !important;
      }

      .status-pad {
        padding: 36px 20px 8px !important;
      }

      .ftr-pad {
        padding: 24px 20px !important;
      }

      .support-pad {
        padding: 14px 18px !important;
      }

      .cta-btn {
        display: block !important;
        width: 100% !important;
        box-sizing: border-box !important;
        text-align: center !important;
      }
    }
  </style>
</head>

<body style="margin:0; padding:0; background-color:#e8e3d8; font-family:Arial, Helvetica, sans-serif;
             -webkit-font-smoothing:antialiased; -moz-osx-font-smoothing:grayscale;">

  <!-- PREHEADER -->
  <div
    style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:#e8e3d8;">
    Спасибо за заказ №{order_number}! Дарите друзьям скидку по личной ссылке — и получайте баллы.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
  </div>

  <!-- OUTER WRAPPER -->
  <table class="email-outer" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
    style="background-color:#e8e3d8; padding:40px 16px;">
    <tr>
      <td align="center" valign="top">

        <!-- EMAIL CARD -->
        <table class="email-card" role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"
          style="max-width:600px; width:100%; background-color:#f7f3ea; border-radius:4px;">


          <!-- ════ HEADER ════ -->
          <tr>
            <td class="hdr-pad" style="background-color:#662132; padding:24px 40px; text-align:center;">
              <a href="https://amaressence.ru/" target="_blank" style="display:inline-block; text-decoration:none;">
                <img src="https://eimage.sendsay.ru/image/x_177486673298052/logo%2Dwhite2.png" alt="amarèssence"
                  width="160" style="display:block; width:160px; max-width:160px; height:auto; border:0; outline:none;">
              </a>
            </td>
          </tr>


          <!-- ════ STATUS ICON + HEADING ════ -->
          <tr>
            <td class="status-pad" style="background-color:#f7f3ea; padding:40px 40px 4px; text-align:center;">

              <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"
                style="margin:0 auto 18px;">
                <tr>
                  <td align="center" valign="middle" style="width:54px; height:54px; background-color:#d0e3ff; border-radius:27px;
                           text-align:center; vertical-align:middle; font-family:Arial, Helvetica, sans-serif;
                           font-size:28px; line-height:54px; color:#662132; font-weight:700;">
                    &#10003;
                  </td>
                </tr>
              </table>

              <h1 style="margin:0 0 10px; font-family:Georgia, 'Times New Roman', serif; font-size:26px;
                          font-weight:400; color:#662132; letter-spacing:0.02em; line-height:1.3;">
                Спасибо за заказ!
              </h1>
            </td>
          </tr>


          <!-- ════ GREETING ════ -->
          <tr>
            <td class="email-pad" style="padding:22px 40px 0;">
              <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:15px;
                         color:#3d3028; line-height:1.75;">
                {customer_first_name}, ваш заказ №{order_number} успешно оформлен в магазине {site_name}. Мы уже
                занимаемся им и сообщим, когда он будет в пути.
              </p>
            </td>
          </tr>


          <!-- ════ REFERRAL INTRO ════ -->
          <tr>
            <td class="email-pad" style="padding:26px 40px 0;">
              <p style="margin:0 0 6px; font-family:Arial, Helvetica, sans-serif; font-size:11px; font-weight:700;
                         letter-spacing:0.14em; color:#662132; text-transform:uppercase; line-height:1;">
                Реферальная программа
              </p>
              <h2 style="margin:0 0 10px; font-family:Georgia, 'Times New Roman', serif; font-size:20px;
                          font-weight:400; color:#662132; line-height:1.3;">
                Дарите скидку — получайте баллы
              </h2>
              <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:14px;
                         color:#3d3028; line-height:1.7;">
                Поделитесь личной ссылкой с друзьями. Когда они оформят первый заказ, вам начислим бонусные баллы —
                <strong style="white-space:nowrap;">1 балл = 1&nbsp;рубль</strong> скидки на следующие покупки.
              </p>
            </td>
          </tr>


          <!-- ════ REFERRAL LINK BLOCK ════ -->
          <tr>
            <td class="email-pad" style="padding:0 40px;">
              {referral_link_block}
            </td>
          </tr>


          <!-- ════ DIVIDER ════ -->
          <tr>
            <td class="email-pad" style="padding:28px 40px 0;">
              <div style="height:1px; background-color:rgba(102,33,50,0.12); line-height:1px; font-size:1px;">&nbsp;
              </div>
            </td>
          </tr>


          <!-- ════ ORDER DETAILS ════ -->
          <tr>
            <td class="email-pad" style="padding:8px 40px 0;">
              {order_details_table}
            </td>
          </tr>


          <!-- ════ SUPPORT BLOCK ════ -->
          <tr>
            <td class="email-pad" style="padding:24px 40px 36px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                style="background-color:#ffffff; border-radius:4px; border:1px solid rgba(102,33,50,0.10);">
                <tr>
                  <td class="support-pad" style="padding:18px 22px;">
                    <p style="margin:0 0 4px; font-family:Arial, Helvetica, sans-serif; font-size:13px;
                               font-weight:600; color:#3d3028;">
                      Есть вопросы по заказу?
                    </p>
                    <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:13px;
                               color:#9a8880; line-height:1.65;">
                      Ответьте на это письмо или напишите нам на
                      <a href="mailto:info@amaressence.ru"
                        style="color:#662132; text-decoration:none; font-weight:600;">info@amaressence.ru</a>.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>


          <!-- ════ FOOTER ════ -->
          <tr>
            <td class="ftr-pad" style="background-color:#662132; padding:28px 40px;
                                        text-align:center; border-radius:0 0 4px 4px;">
              <a href="https://amaressence.ru/" target="_blank" style="display:inline-block; text-decoration:none;">
                <img src="https://eimage.sendsay.ru/image/x_177486673298052/logo%2Dwhite2.png" alt="amarèssence"
                  width="120" style="display:block; width:120px; max-width:120px; height:auto;
                            border:0; outline:none; opacity:0.9; margin:0 auto 16px;">
              </a>

              <p style="margin:0 0 16px; font-size:0; line-height:0;">
                <a href="https://vk.com/amaressence" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:10px; font-weight:600;
                         letter-spacing:0.12em; color:#f0e6ea; text-decoration:none;
                         text-transform:uppercase; margin:0 8px;">VK</a>
                <a href="https://t.me/amaressence" target="_blank" style="font-family:Arial, Helvetica, sans-serif; font-size:10px; font-weight:600;
                         letter-spacing:0.12em; color:#f0e6ea; text-decoration:none;
                         text-transform:uppercase; margin:0 8px;">Telegram</a>
              </p>

              <p style="margin:0 0 6px; font-family:Arial, Helvetica, sans-serif; font-size:11px;
                         color:#e9dbe0; line-height:1.65;">
                Вы получили это письмо, потому что оформили заказ на сайте amarèssence.
              </p>
              <p style="margin:0; font-family:Arial, Helvetica, sans-serif; font-size:10px;
                         color:#c9a9b4; line-height:1.5;">
                © 2026 amarèssence. Все права защищены.
              </p>
            </td>
          </tr>


        </table>
        <!-- /EMAIL CARD -->

      </td>
    </tr>
  </table>

</body>

</html>
HTML;
	}

	/**
	 * Get default email templates.
	 *
	 * @return array
	 */
	public static function email_template_defaults() {
		return array(
			'first_order_referral' => array(
				'enabled'               => 'yes',
				'send_order_status'     => 'paid',
				'subject'               => 'Спасибо за ваш заказ #{order_number} — ваша реферальная ссылка',
				'body'                  => self::first_order_email_body_default(),
				'css'                   => '',
				'append_order_details'  => 'no',
				'append_referral_block' => 'no',
				'blocks'                => array(
					'order_details_table' => '<h2 style="margin:20px 0 12px; font-family:Georgia, \'Times New Roman\', serif; font-size:18px; font-weight:400; color:#662132;">{label_order_details}</h2>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border-collapse:collapse; margin-bottom:18px; font-family:Arial, Helvetica, sans-serif; font-size:14px; color:#3d3028;">
	<tr><td style="padding:9px 0; border-bottom:1px solid rgba(102,33,50,0.12);">{label_order_number}</td><td style="padding:9px 0; border-bottom:1px solid rgba(102,33,50,0.12); text-align:right; font-weight:700;">{order_number}</td></tr>
	<tr><td style="padding:9px 0; border-bottom:1px solid rgba(102,33,50,0.12);">{label_order_date}</td><td style="padding:9px 0; border-bottom:1px solid rgba(102,33,50,0.12); text-align:right;">{order_date}</td></tr>
	<tr><td style="padding:9px 0;">{label_order_total}</td><td style="padding:9px 0; text-align:right; font-weight:700; color:#662132;">{order_total}</td></tr>
</table>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; border-collapse:collapse; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#3d3028;">
	<thead>
		<tr>
			<th style="padding:8px 10px; text-align:left; background-color:#efe7d9; color:#662132; font-size:11px; letter-spacing:0.06em; text-transform:uppercase;">{label_product}</th>
			<th style="padding:8px 10px; text-align:center; background-color:#efe7d9; color:#662132; font-size:11px; letter-spacing:0.06em; text-transform:uppercase;">{label_quantity}</th>
			<th style="padding:8px 10px; text-align:right; background-color:#efe7d9; color:#662132; font-size:11px; letter-spacing:0.06em; text-transform:uppercase;">{label_amount}</th>
		</tr>
	</thead>
	<tbody>{order_items_rows}</tbody>
</table>',
					'order_item_row'      => '<tr><td style="padding:9px 10px; border-bottom:1px solid rgba(102,33,50,0.10);">{item_name}</td><td style="padding:9px 10px; border-bottom:1px solid rgba(102,33,50,0.10); text-align:center;">{item_quantity}</td><td style="padding:9px 10px; border-bottom:1px solid rgba(102,33,50,0.10); text-align:right;">{item_subtotal}</td></tr>',
					'referral_link_block' => '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; margin:20px 0 0;">
	<tr>
		<td style="background-color:#d0e3ff; border-radius:4px; padding:24px 28px; text-align:center;">
			<p style="margin:0 0 6px; font-family:Arial, Helvetica, sans-serif; font-size:11px; font-weight:700; letter-spacing:0.14em; color:#662132; text-transform:uppercase; line-height:1;">{label_referral_link}</p>
			<p style="margin:0 0 18px; font-family:Arial, Helvetica, sans-serif; font-size:13px; color:#3d3028; line-height:1.6;">Скопируйте ссылку или отправьте её другу одним нажатием.</p>
			<a class="cta-btn" href="{referral_link}" target="_blank" style="display:inline-block; background-color:#662132; color:#f7f3ea; font-family:Arial, Helvetica, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.12em; text-decoration:none; text-transform:uppercase; padding:15px 44px; border-radius:2px;">Поделиться ссылкой</a>
			<p style="margin:16px 0 0; font-family:Arial, Helvetica, sans-serif; font-size:13px; line-height:1.5; word-break:break-all;"><a href="{referral_link}" target="_blank" style="color:#662132; text-decoration:underline;">{referral_link}</a></p>
		</td>
	</tr>
</table>',
				),
			),
			'points_expiring' => array(
				'enabled'               => 'yes',
				'send_order_status'     => '',
				'subject'               => 'Ваши баллы скоро сгорят — amarèssence',
				'body'                  => self::points_expiring_email_body_default(),
				'css'                   => '',
				'append_order_details'  => 'no',
				'append_referral_block' => 'no',
				'blocks'                => array(),
			),
		);
	}

	/**
	 * Get default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		$email_templates = self::email_template_defaults();
		$first_email     = $email_templates['first_order_referral'];

		return array(
			'plugin_enabled'               => 'yes',
			'logs_enabled'                 => 'yes',
			'referrals_enabled'            => 'yes',
			'referral_url_param'           => 'ref',
			'referral_cookie_days'         => 30,
			'prevent_self_referral'        => 'yes',
			'allow_guest_participation'    => 'yes',
			'points_enabled'               => 'yes',
			'reward_rate'                  => '10',
			'reward_order_status'          => 'completed',
			'reverse_on_cancel'            => 'yes',
			'reverse_on_refund'            => 'yes',
			'self_points_enabled'          => 'yes',
			'self_points_reward_type'      => 'percent',
			'self_points_reward_value'     => '10',
			'self_points_order_status'     => 'completed',
			'self_points_min_order_total'  => '0',
			'self_points_max_per_order'    => '0',
			'self_points_require_user'     => 'no',
			'self_points_skip_if_referral' => 'yes',
			'self_points_reverse_on_cancel'=> 'yes',
			'self_points_reverse_on_refund'=> 'yes',
			'checkout_points_redeem_enabled'       => 'yes',
			'checkout_points_max_percent'          => '100',
			'checkout_points_include_shipping'     => 'yes',
			'checkout_points_allow_shipping_coupon'=> 'yes',
			'points_expiration_enabled'    => 'no',
			'points_expiration_mode'       => 'rolling_days',
			'points_expiration_days'       => 365,
			'points_expiration_fixed_day'  => 31,
			'points_expiration_fixed_month'=> 12,
			'points_expiration_notice_days'=> 7,
			'points_expiration_run_hour'   => 9,
			'points_expiration_batch_limit'=> 200,
			'email_enabled'                => 'yes',
			'email_send_order_status'      => $first_email['send_order_status'],
			'email_subject'                => $first_email['subject'],
			'email_body'                   => $first_email['body'],
			'email_css'                    => $first_email['css'],
			'email_append_order_details'   => $first_email['append_order_details'],
			'email_append_referral_block'  => $first_email['append_referral_block'],
			'email_templates'              => $email_templates,
			'account_endpoint'             => 'referrals',
			'account_intro_text'           => 'Поделитесь своей ссылкой с новым покупателем. Баллы начисляются после первого заказа приглашённого клиента, когда заказ достигает выбранного в настройках статуса.',
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_all() {
		$stored = get_option( $this->option_key, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings                    = wp_parse_args( $stored, self::defaults() );
		$settings['email_templates'] = $this->normalize_email_templates( isset( $stored['email_templates'] ) ? $stored['email_templates'] : array(), $settings );

		if ( isset( $settings['email_templates']['first_order_referral'] ) ) {
			$first_email                             = $settings['email_templates']['first_order_referral'];
			$settings['email_send_order_status']     = $first_email['send_order_status'];
			$settings['email_subject']               = $first_email['subject'];
			$settings['email_body']                  = $first_email['body'];
			$settings['email_css']                   = $first_email['css'];
			$settings['email_append_order_details']  = $first_email['append_order_details'];
			$settings['email_append_referral_block'] = $first_email['append_referral_block'];
		}

		if ( ! array_key_exists( 'self_points_reward_value', $stored ) ) {
			$settings['self_points_reward_value'] = isset( $stored['reward_rate'] ) ? $stored['reward_rate'] : self::defaults()['self_points_reward_value'];
		}

		if ( ! array_key_exists( 'self_points_order_status', $stored ) ) {
			$settings['self_points_order_status'] = isset( $stored['reward_order_status'] ) ? $stored['reward_order_status'] : self::defaults()['self_points_order_status'];
		}

		if ( ! array_key_exists( 'self_points_reverse_on_cancel', $stored ) ) {
			$settings['self_points_reverse_on_cancel'] = isset( $stored['reverse_on_cancel'] ) ? $stored['reverse_on_cancel'] : self::defaults()['self_points_reverse_on_cancel'];
		}

		if ( ! array_key_exists( 'self_points_reverse_on_refund', $stored ) ) {
			$settings['self_points_reverse_on_refund'] = isset( $stored['reverse_on_refund'] ) ? $stored['reverse_on_refund'] : self::defaults()['self_points_reverse_on_refund'];
		}

		return $settings;
	}

	/**
	 * Normalize saved email templates and migrate legacy single-email fields.
	 *
	 * @param array $templates Saved templates.
	 * @param array $settings  Current settings with legacy fields.
	 * @return array
	 */
	protected function normalize_email_templates( $templates, $settings ) {
		$defaults   = self::email_template_defaults();
		$normalized = array();

		if ( ! is_array( $templates ) ) {
			$templates = array();
		}

		foreach ( $defaults as $type => $default_template ) {
			$legacy = array();

			if ( 'first_order_referral' === $type ) {
				$legacy = array(
					'enabled'               => isset( $settings['email_enabled'] ) ? $settings['email_enabled'] : $default_template['enabled'],
					'send_order_status'     => isset( $settings['email_send_order_status'] ) ? $settings['email_send_order_status'] : $default_template['send_order_status'],
					'subject'               => isset( $settings['email_subject'] ) ? $settings['email_subject'] : $default_template['subject'],
					'body'                  => isset( $settings['email_body'] ) ? $settings['email_body'] : $default_template['body'],
					'css'                   => isset( $settings['email_css'] ) ? $settings['email_css'] : $default_template['css'],
					'append_order_details'  => isset( $settings['email_append_order_details'] ) ? $settings['email_append_order_details'] : $default_template['append_order_details'],
					'append_referral_block' => isset( $settings['email_append_referral_block'] ) ? $settings['email_append_referral_block'] : $default_template['append_referral_block'],
				);
			}

			$source = isset( $templates[ $type ] ) && is_array( $templates[ $type ] ) ? $templates[ $type ] : $legacy;
			$source = wp_parse_args( $source, $default_template );

			if ( empty( $source['blocks'] ) || ! is_array( $source['blocks'] ) ) {
				$source['blocks'] = array();
			}

			$source['blocks']       = wp_parse_args( $source['blocks'], $default_template['blocks'] );
			$normalized[ $type ] = $source;
		}

		return $normalized;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$settings = $this->get_all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		return $default;
	}

	/**
	 * Update settings.
	 *
	 * @param array $data Raw settings data.
	 * @return array
	 */
	public function update( $data ) {
		$current          = $this->get_all();
		$email_templates  = $this->sanitize_email_templates( isset( $data['email_templates'] ) ? $data['email_templates'] : $current['email_templates'], $current['email_templates'] );
		$first_email      = isset( $email_templates['first_order_referral'] ) ? $email_templates['first_order_referral'] : self::email_template_defaults()['first_order_referral'];
		$email_enabled    = $this->sanitize_checkbox( isset( $data['email_enabled'] ) ? $data['email_enabled'] : $current['email_enabled'] );
		$updated = array(
			'plugin_enabled'               => $this->sanitize_checkbox( isset( $data['plugin_enabled'] ) ? $data['plugin_enabled'] : $current['plugin_enabled'] ),
			'logs_enabled'                 => $this->sanitize_checkbox( isset( $data['logs_enabled'] ) ? $data['logs_enabled'] : $current['logs_enabled'] ),
			'referrals_enabled'            => $this->sanitize_checkbox( isset( $data['referrals_enabled'] ) ? $data['referrals_enabled'] : $current['referrals_enabled'] ),
			'referral_url_param'           => $this->sanitize_url_param( isset( $data['referral_url_param'] ) ? $data['referral_url_param'] : $current['referral_url_param'] ),
			'referral_cookie_days'         => $this->sanitize_cookie_days( isset( $data['referral_cookie_days'] ) ? $data['referral_cookie_days'] : $current['referral_cookie_days'] ),
			'prevent_self_referral'        => $this->sanitize_checkbox( isset( $data['prevent_self_referral'] ) ? $data['prevent_self_referral'] : $current['prevent_self_referral'] ),
			'allow_guest_participation'    => $this->sanitize_checkbox( isset( $data['allow_guest_participation'] ) ? $data['allow_guest_participation'] : $current['allow_guest_participation'] ),
			'points_enabled'               => $this->sanitize_checkbox( isset( $data['points_enabled'] ) ? $data['points_enabled'] : $current['points_enabled'] ),
			'reward_rate'                  => $this->sanitize_reward_rate( isset( $data['reward_rate'] ) ? $data['reward_rate'] : $current['reward_rate'] ),
			'reward_order_status'          => $this->sanitize_order_status( isset( $data['reward_order_status'] ) ? $data['reward_order_status'] : $current['reward_order_status'] ),
			'reverse_on_cancel'            => $this->sanitize_checkbox( isset( $data['reverse_on_cancel'] ) ? $data['reverse_on_cancel'] : $current['reverse_on_cancel'] ),
			'reverse_on_refund'            => $this->sanitize_checkbox( isset( $data['reverse_on_refund'] ) ? $data['reverse_on_refund'] : $current['reverse_on_refund'] ),
			'self_points_enabled'          => $this->sanitize_checkbox( isset( $data['self_points_enabled'] ) ? $data['self_points_enabled'] : $current['self_points_enabled'] ),
			'self_points_reward_type'      => $this->sanitize_reward_type( isset( $data['self_points_reward_type'] ) ? $data['self_points_reward_type'] : $current['self_points_reward_type'] ),
			'self_points_reward_value'     => $this->sanitize_non_negative_decimal( isset( $data['self_points_reward_value'] ) ? $data['self_points_reward_value'] : $current['self_points_reward_value'] ),
			'self_points_order_status'     => $this->sanitize_order_status( isset( $data['self_points_order_status'] ) ? $data['self_points_order_status'] : $current['self_points_order_status'] ),
			'self_points_min_order_total'  => $this->sanitize_non_negative_decimal( isset( $data['self_points_min_order_total'] ) ? $data['self_points_min_order_total'] : $current['self_points_min_order_total'] ),
			'self_points_max_per_order'    => $this->sanitize_non_negative_decimal( isset( $data['self_points_max_per_order'] ) ? $data['self_points_max_per_order'] : $current['self_points_max_per_order'] ),
			'self_points_require_user'     => $this->sanitize_checkbox( isset( $data['self_points_require_user'] ) ? $data['self_points_require_user'] : $current['self_points_require_user'] ),
			'self_points_skip_if_referral' => $this->sanitize_checkbox( isset( $data['self_points_skip_if_referral'] ) ? $data['self_points_skip_if_referral'] : $current['self_points_skip_if_referral'] ),
			'self_points_reverse_on_cancel'=> $this->sanitize_checkbox( isset( $data['self_points_reverse_on_cancel'] ) ? $data['self_points_reverse_on_cancel'] : $current['self_points_reverse_on_cancel'] ),
			'self_points_reverse_on_refund'=> $this->sanitize_checkbox( isset( $data['self_points_reverse_on_refund'] ) ? $data['self_points_reverse_on_refund'] : $current['self_points_reverse_on_refund'] ),
			'checkout_points_redeem_enabled'       => $this->sanitize_checkbox( isset( $data['checkout_points_redeem_enabled'] ) ? $data['checkout_points_redeem_enabled'] : $current['checkout_points_redeem_enabled'] ),
			'checkout_points_max_percent'          => $this->sanitize_reward_rate( isset( $data['checkout_points_max_percent'] ) ? $data['checkout_points_max_percent'] : $current['checkout_points_max_percent'] ),
			'checkout_points_include_shipping'     => $this->sanitize_checkbox( isset( $data['checkout_points_include_shipping'] ) ? $data['checkout_points_include_shipping'] : $current['checkout_points_include_shipping'] ),
			'checkout_points_allow_shipping_coupon'=> $this->sanitize_checkbox( isset( $data['checkout_points_allow_shipping_coupon'] ) ? $data['checkout_points_allow_shipping_coupon'] : $current['checkout_points_allow_shipping_coupon'] ),
			'points_expiration_enabled'    => $this->sanitize_checkbox( isset( $data['points_expiration_enabled'] ) ? $data['points_expiration_enabled'] : $current['points_expiration_enabled'] ),
			'points_expiration_mode'       => $this->sanitize_expiration_mode( isset( $data['points_expiration_mode'] ) ? $data['points_expiration_mode'] : $current['points_expiration_mode'] ),
			'points_expiration_days'       => $this->sanitize_positive_int( isset( $data['points_expiration_days'] ) ? $data['points_expiration_days'] : $current['points_expiration_days'], 1, 3650, 365 ),
			'points_expiration_fixed_day'  => $this->sanitize_positive_int( isset( $data['points_expiration_fixed_day'] ) ? $data['points_expiration_fixed_day'] : $current['points_expiration_fixed_day'], 1, 31, 31 ),
			'points_expiration_fixed_month'=> $this->sanitize_positive_int( isset( $data['points_expiration_fixed_month'] ) ? $data['points_expiration_fixed_month'] : $current['points_expiration_fixed_month'], 1, 12, 12 ),
			'points_expiration_notice_days'=> $this->sanitize_positive_int( isset( $data['points_expiration_notice_days'] ) ? $data['points_expiration_notice_days'] : $current['points_expiration_notice_days'], 0, 365, 7 ),
			'points_expiration_run_hour'   => $this->sanitize_positive_int( isset( $data['points_expiration_run_hour'] ) ? $data['points_expiration_run_hour'] : $current['points_expiration_run_hour'], 0, 23, 9 ),
			'points_expiration_batch_limit'=> $this->sanitize_positive_int( isset( $data['points_expiration_batch_limit'] ) ? $data['points_expiration_batch_limit'] : $current['points_expiration_batch_limit'], 10, 1000, 200 ),
			'email_enabled'                => $email_enabled,
			'email_send_order_status'      => $first_email['send_order_status'],
			'email_subject'                => $first_email['subject'],
			'email_body'                   => $first_email['body'],
			'email_css'                    => $first_email['css'],
			'email_append_order_details'   => $first_email['append_order_details'],
			'email_append_referral_block'  => $first_email['append_referral_block'],
			'email_templates'              => $email_templates,
			'account_endpoint'             => $this->sanitize_endpoint( isset( $data['account_endpoint'] ) ? $data['account_endpoint'] : $current['account_endpoint'] ),
			'account_intro_text'           => $this->sanitize_account_text( isset( $data['account_intro_text'] ) ? $data['account_intro_text'] : $current['account_intro_text'] ),
		);

		update_option( $this->option_key, $updated );

		return $updated;
	}

	/**
	 * Ensure defaults exist.
	 *
	 * @return void
	 */
	public function maybe_add_defaults() {
		if ( false === get_option( $this->option_key, false ) ) {
			add_option( $this->option_key, self::defaults() );
		}
	}

	/**
	 * Sanitize checkbox-like setting value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function sanitize_checkbox( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( '1', 'yes', 'on', 'true' ), true ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize email templates.
	 *
	 * @param mixed $value   Raw templates.
	 * @param array $current Current templates.
	 * @return array
	 */
	protected function sanitize_email_templates( $value, $current = array() ) {
		$defaults = self::email_template_defaults();
		$result   = array();

		if ( ! is_array( $value ) ) {
			$value = array();
		}

		if ( ! is_array( $current ) ) {
			$current = array();
		}

		foreach ( $defaults as $type => $default_template ) {
			$source = isset( $value[ $type ] ) && is_array( $value[ $type ] ) ? $value[ $type ] : array();
			$source = wp_parse_args( $source, isset( $current[ $type ] ) && is_array( $current[ $type ] ) ? $current[ $type ] : $default_template );
			$source = wp_parse_args( $source, $default_template );

			$result[ $type ] = array(
				'enabled'               => $this->sanitize_checkbox( isset( $source['enabled'] ) ? $source['enabled'] : $default_template['enabled'] ),
				'send_order_status'     => ! empty( $default_template['send_order_status'] ) ? $this->sanitize_email_send_status( isset( $source['send_order_status'] ) ? $source['send_order_status'] : $default_template['send_order_status'] ) : '',
				'subject'               => $this->sanitize_subject( isset( $source['subject'] ) ? $source['subject'] : $default_template['subject'], $default_template['subject'] ),
				'body'                  => $this->sanitize_email_body( isset( $source['body'] ) ? $source['body'] : $default_template['body'], $default_template['body'] ),
				'css'                   => $this->sanitize_email_css( isset( $source['css'] ) ? $source['css'] : $default_template['css'] ),
				'append_order_details'  => $this->sanitize_checkbox( isset( $source['append_order_details'] ) ? $source['append_order_details'] : $default_template['append_order_details'] ),
				'append_referral_block' => $this->sanitize_checkbox( isset( $source['append_referral_block'] ) ? $source['append_referral_block'] : $default_template['append_referral_block'] ),
				'blocks'                => $this->sanitize_email_template_blocks( isset( $source['blocks'] ) ? $source['blocks'] : array(), $default_template['blocks'] ),
			);
		}

		return $result;
	}

	/**
	 * Sanitize editable email sub-blocks.
	 *
	 * @param mixed $blocks         Raw blocks.
	 * @param array $default_blocks Default blocks.
	 * @return array
	 */
	protected function sanitize_email_template_blocks( $blocks, $default_blocks ) {
		$result = array();

		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}

		foreach ( $default_blocks as $block_key => $default_html ) {
			$result[ $block_key ] = $this->sanitize_email_body( isset( $blocks[ $block_key ] ) ? $blocks[ $block_key ] : $default_html, $default_html );
		}

		return $result;
	}

	/**
	 * Sanitize URL parameter.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_url_param( $value ) {
		$value = sanitize_key( $value );

		if ( empty( $value ) ) {
			$value = self::defaults()['referral_url_param'];
		}

		return substr( $value, 0, 32 );
	}

	/**
	 * Sanitize cookie days.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	protected function sanitize_cookie_days( $value ) {
		$value = absint( $value );

		if ( $value < 1 ) {
			$value = 30;
		}

		if ( $value > 365 ) {
			$value = 365;
		}

		return $value;
	}

	/**
	 * Sanitize points expiration mode.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_expiration_mode( $value ) {
		$value = sanitize_key( (string) $value );

		if ( ! in_array( $value, array( 'rolling_days', 'fixed_date' ), true ) ) {
			$value = self::defaults()['points_expiration_mode'];
		}

		return $value;
	}

	/**
	 * Sanitize integer in a range.
	 *
	 * @param mixed $value Raw value.
	 * @param int   $min Minimum.
	 * @param int   $max Maximum.
	 * @param int   $default Default.
	 * @return int
	 */
	protected function sanitize_positive_int( $value, $min, $max, $default ) {
		$value = absint( $value );

		if ( $value < $min ) {
			$value = $default;
		}

		if ( $value > $max ) {
			$value = $max;
		}

		return $value;
	}

	/**
	 * Sanitize reward rate.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function sanitize_reward_rate( $value ) {
		$value = wc_format_decimal( $value, 4 );
		$value = (float) $value;

		if ( $value < 0 ) {
			$value = 0;
		}

		if ( $value > 100 ) {
			$value = 100;
		}

		return (string) $value;
	}

	/**
	 * Sanitize reward calculation type.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_reward_type( $value ) {
		$value = sanitize_key( (string) $value );

		if ( ! in_array( $value, array( 'percent', 'fixed' ), true ) ) {
			$value = self::defaults()['self_points_reward_type'];
		}

		return $value;
	}

	/**
	 * Sanitize a non-negative decimal field.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function sanitize_non_negative_decimal( $value ) {
		$value = wc_format_decimal( $value, 4 );
		$value = (float) $value;

		if ( $value < 0 ) {
			$value = 0;
		}

		return (string) $value;
	}

	/**
	 * Sanitize order status.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_order_status( $value ) {
		$value    = sanitize_key( str_replace( 'wc-', '', (string) $value ) );
		$statuses = array_keys( wc_get_order_statuses() );
		$allowed  = array_map(
			function( $status ) {
				return str_replace( 'wc-', '', $status );
			},
			$statuses
		);

		if ( ! in_array( $value, $allowed, true ) ) {
			$value = self::defaults()['reward_order_status'];
		}

		return $value;
	}

	/**
	 * Sanitize email sending status.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_email_send_status( $value ) {
		$value = sanitize_key( str_replace( 'wc-', '', (string) $value ) );

		if ( 'paid' === $value ) {
			return 'paid';
		}

		$statuses = array_keys( wc_get_order_statuses() );
		$allowed  = array_map(
			function( $status ) {
				return str_replace( 'wc-', '', $status );
			},
			$statuses
		);

		if ( ! in_array( $value, $allowed, true ) ) {
			$value = self::defaults()['email_send_order_status'];
		}

		return $value;
	}

	/**
	 * Sanitize email subject.
	 *
	 * @param string      $value   Raw value.
	 * @param string|null $default Optional default.
	 * @return string
	 */
	protected function sanitize_subject( $value, $default = null ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = trim( $value );

		if ( '' === $value ) {
			$value = null !== $default ? $default : self::defaults()['email_subject'];
		}

		return $value;
	}

	/**
	 * Sanitize email body.
	 *
	 * @param string      $value   Raw value.
	 * @param string|null $default Optional default.
	 * @return string
	 */
	protected function sanitize_email_body( $value, $default = null ) {
		$value = (string) $value;
		$value = preg_replace( '#<script\b[^>]*>.*?</script>#is', '', $value );
		$value = preg_replace( '/\son[a-z]+\s*=\s*("|\').*?\1/isu', '', $value );
		$value = preg_replace( '/\s(href|src)\s*=\s*("|\')\s*javascript:[^"\']*\2/isu', ' $1="#"', $value );
		$value = trim( $value );

		if ( '' === $value ) {
			$value = null !== $default ? $default : self::defaults()['email_body'];
		}

		return $value;
	}

	/**
	 * Sanitize email CSS. Stored as plain CSS without <style> tags.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_email_css( $value ) {
		$value = wp_strip_all_tags( (string) $value );
		$value = trim( $value );

		if ( strlen( $value ) > 30000 ) {
			$value = substr( $value, 0, 30000 );
		}

		return $value;
	}

	/**
	 * Sanitize endpoint.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_endpoint( $value ) {
		$value = sanitize_title( $value );

		if ( '' === $value ) {
			$value = self::defaults()['account_endpoint'];
		}

		return substr( $value, 0, 32 );
	}

	/**
	 * Sanitize account text.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function sanitize_account_text( $value ) {
		$value = wp_kses_post( (string) $value );
		$value = trim( $value );

		if ( '' === $value ) {
			$value = self::defaults()['account_intro_text'];
		}

		return $value;
	}
}
