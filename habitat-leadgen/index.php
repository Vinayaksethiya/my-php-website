<?php
/**
 * Fallback template — this theme is built around its 7 "Habitat LeadGen"
 * page templates. Create a Page, assign one of them, and view that page.
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div style="max-width:640px;margin:80px auto;font-family:Arial,sans-serif;text-align:center">
  <h1>Habitat LeadGen</h1>
  <p>This theme's landing pages live on WordPress Pages using the
  <b>Habitat LeadGen</b> page templates (Pages &gt; Add New &gt; Template).</p>
</div>
<?php wp_footer(); ?>
</body>
</html>
