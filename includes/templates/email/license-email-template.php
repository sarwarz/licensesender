<?php
/**
 * License Email Template v2
 *
 * @var string $site_name
 * @var string $subject
 * @var string $headline
 * @var string $intro
 * @var string $preheader
 * @var string $brand_color
 * @var string $accent_color
 * @var string $text_color
 * @var string $muted_color
 * @var string $bg_color
 * @var string $card_bg
 * @var string $border_color
 * @var string $logo_url
 * @var string $support_email
 * @var string $customer_name
 * @var string $order_number
 * @var string $order_date
 * @var string $rows_html
 */
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="x-apple-disable-message-reformatting">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $subject ); ?></title>
<style type="text/css">
@media only screen and (max-width: 600px) {
    .ls-email-table thead { display: none !important; }
    .ls-email-license-row { display: block !important; border: 1px solid <?php echo esc_attr( $border_color ); ?> !important; border-radius: 10px !important; margin-bottom: 12px !important; overflow: hidden !important; }
    .ls-email-license-row td { display: block !important; width: 100% !important; text-align: left !important; box-sizing: border-box !important; border: 0 !important; }
    .ls-email-col-product { font-weight: 600 !important; background: #F9FAFB !important; border-bottom: 1px solid <?php echo esc_attr( $border_color ); ?> !important; }
    .ls-email-col-key::before { content: "<?php echo esc_attr__( 'License Key', 'licensesender' ); ?>"; display: block; font: 600 11px/16px Arial,sans-serif; text-transform: uppercase; letter-spacing: .04em; color: <?php echo esc_attr( $muted_color ); ?>; margin-bottom: 6px; }
    .ls-email-col-download::before { content: "<?php echo esc_attr__( 'Download', 'licensesender' ); ?>"; display: block; font: 600 11px/16px Arial,sans-serif; text-transform: uppercase; letter-spacing: .04em; color: <?php echo esc_attr( $muted_color ); ?>; margin-bottom: 6px; }
    .ls-email-col-guide::before { content: "<?php echo esc_attr__( 'Guide', 'licensesender' ); ?>"; display: block; font: 600 11px/16px Arial,sans-serif; text-transform: uppercase; letter-spacing: .04em; color: <?php echo esc_attr( $muted_color ); ?>; margin-bottom: 6px; }
    .ls-email-col-download a, .ls-email-col-guide a { width: 100% !important; text-align: center !important; box-sizing: border-box !important; }
    .ls-email-order-badge { display: block !important; margin-top: 8px !important; text-align: left !important; }
}
</style>
</head>
<body style="margin:0; padding:0; background:<?php echo esc_attr( $bg_color ); ?>;">
    <span style="display:none !important; visibility:hidden; opacity:0; height:0; width:0; overflow:hidden; mso-hide:all; color:transparent;">
        <?php echo esc_html( $preheader ); ?>
    </span>

    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:<?php echo esc_attr( $bg_color ); ?>;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="max-width:640px; width:100%;">

                    <tr>
                        <td style="padding:20px 24px; background:<?php echo esc_attr( $card_bg ); ?>; border-radius:12px 12px 0 0; border:1px solid <?php echo esc_attr( $border_color ); ?>; border-bottom:0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <?php if ( $logo_url ) : ?>
                                            <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" height="36" style="display:block; height:36px; max-width:160px;">
                                        <?php else : ?>
                                            <div style="font:bold 20px/28px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:<?php echo esc_attr( $text_color ); ?>;">
                                                <?php echo esc_html( $site_name ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td align="right" class="ls-email-order-badge" style="vertical-align:middle;">
                                        <span style="display:inline-block; padding:6px 10px; border-radius:999px; background:<?php echo esc_attr( $brand_color ); ?>; color:#fff; font:12px/16px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
                                            <?php echo esc_html( sprintf( __( 'Order #%s', 'licensesender' ), $order_number ) ); ?>
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:<?php echo esc_attr( $card_bg ); ?>; border-left:1px solid <?php echo esc_attr( $border_color ); ?>; border-right:1px solid <?php echo esc_attr( $border_color ); ?>;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:8px 24px 0 24px;">
                                        <h1 style="margin:16px 0 8px 0; font:600 22px/30px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:<?php echo esc_attr( $text_color ); ?>;">
                                            <?php echo esc_html( sprintf( __( 'Hi %s,', 'licensesender' ), $customer_name ) ); ?> <?php echo esc_html( lcfirst( $headline ) ); ?>
                                        </h1>
                                        <p style="margin:0 0 8px 0; font:14px/22px -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; color:<?php echo esc_attr( $muted_color ); ?>;">
                                            <?php echo esc_html( $intro ); ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 24px 24px 24px;">
                                        <table role="presentation" class="ls-email-table" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid <?php echo esc_attr( $border_color ); ?>; border-radius:10px; overflow:hidden;">
                                            <thead>
                                                <tr>
                                                    <th align="left" style="padding:12px 14px; font:600 12px/16px Arial,sans-serif; letter-spacing:.02em; text-transform:uppercase; color:<?php echo esc_attr( $muted_color ); ?>; background:#F9FAFB; border-bottom:1px solid <?php echo esc_attr( $border_color ); ?>;"><?php esc_html_e( 'Product', 'licensesender' ); ?></th>
                                                    <th align="left" style="padding:12px 14px; font:600 12px/16px Arial,sans-serif; letter-spacing:.02em; text-transform:uppercase; color:<?php echo esc_attr( $muted_color ); ?>; background:#F9FAFB; border-bottom:1px solid <?php echo esc_attr( $border_color ); ?>;"><?php esc_html_e( 'License Key', 'licensesender' ); ?></th>
                                                    <th align="center" style="padding:12px 14px; font:600 12px/16px Arial,sans-serif; letter-spacing:.02em; text-transform:uppercase; color:<?php echo esc_attr( $muted_color ); ?>; background:#F9FAFB; border-bottom:1px solid <?php echo esc_attr( $border_color ); ?>;"><?php esc_html_e( 'Download', 'licensesender' ); ?></th>
                                                    <th align="center" style="padding:12px 14px; font:600 12px/16px Arial,sans-serif; letter-spacing:.02em; text-transform:uppercase; color:<?php echo esc_attr( $muted_color ); ?>; background:#F9FAFB; border-bottom:1px solid <?php echo esc_attr( $border_color ); ?>;"><?php esc_html_e( 'Guide', 'licensesender' ); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaped values ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 24px 24px 24px;">
                                        <p style="margin:0; font:14px/22px Arial,sans-serif; color:<?php echo esc_attr( $muted_color ); ?>;">
                                            <?php esc_html_e( 'Need help?', 'licensesender' ); ?>
                                            <?php esc_html_e( 'Contact us at', 'licensesender' ); ?>
                                            <a href="mailto:<?php echo esc_attr( $support_email ); ?>" style="color:<?php echo esc_attr( $brand_color ); ?>; text-decoration:none;"><?php echo esc_html( $support_email ); ?></a>.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 24px 28px 24px; background:<?php echo esc_attr( $card_bg ); ?>; border:1px solid <?php echo esc_attr( $border_color ); ?>; border-top:0; border-radius:0 0 12px 12px;">
                            <p style="margin:0; font:12px/18px Arial,sans-serif; color:<?php echo esc_attr( $muted_color ); ?>;">
                                <?php echo esc_html( $site_name ); ?> • <a href="<?php echo esc_url( home_url() ); ?>" target="_blank" rel="noopener noreferrer" style="color:<?php echo esc_attr( $muted_color ); ?>; text-decoration:none;"><?php echo esc_html( home_url() ); ?></a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
