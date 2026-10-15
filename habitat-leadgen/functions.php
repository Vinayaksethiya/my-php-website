<?php
/**
 * Habitat LeadGen theme setup.
 */

/* ---------- Database table creation on theme activation ---------- */

function hlead_create_leads_table() {
  global $wpdb;
  $table = $wpdb->prefix . 'leadgen_leads';
  $charset = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE IF NOT EXISTS $table (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_slug     VARCHAR(80)     NOT NULL DEFAULT '',
    full_name     VARCHAR(160)    NOT NULL DEFAULT '',
    phone         VARCHAR(40)     NOT NULL DEFAULT '',
    email         VARCHAR(160)    NOT NULL DEFAULT '',
    zip           VARCHAR(20)     NOT NULL DEFAULT '',
    topic         VARCHAR(120)    NOT NULL DEFAULT '',
    details       TEXT                     DEFAULT NULL,
    ip_address    VARCHAR(45)     NOT NULL DEFAULT '',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_slug  (page_slug),
    KEY idx_email (email(80))
  ) $charset;";
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  dbDelta( $sql );
}
add_action( 'after_switch_theme', 'hlead_create_leads_table' );
add_action( 'init',               'hlead_create_leads_table' ); // also runs on every load so table exists even if theme wasn't re-activated

/* ---------- AJAX handler — save lead from demo form ---------- */

add_action( 'wp_ajax_hlead_save_lead',        'hlead_save_lead_handler' );
add_action( 'wp_ajax_nopriv_hlead_save_lead', 'hlead_save_lead_handler' );

function hlead_save_lead_handler() {
  check_ajax_referer( 'hlead_lead_nonce', 'nonce' );

  global $wpdb;
  $table = $wpdb->prefix . 'leadgen_leads';

  $data = array(
    'page_slug' => sanitize_key( wp_unslash( $_POST['page_slug'] ?? '' ) ),
    'full_name' => sanitize_text_field( wp_unslash( $_POST['name']      ?? '' ) ),
    'phone'     => sanitize_text_field( wp_unslash( $_POST['phone']     ?? '' ) ),
    'email'     => sanitize_email(      wp_unslash( $_POST['email']     ?? '' ) ),
    'zip'       => sanitize_text_field( wp_unslash( $_POST['zip']       ?? '' ) ),
    'topic'     => sanitize_text_field( wp_unslash( $_POST['topic']     ?? '' ) ),
    'details'   => sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) ),
    'ip_address'=> sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
  );

  // Basic required-field validation
  if ( empty( $data['full_name'] ) || empty( $data['phone'] ) || empty( $data['email'] ) ) {
    wp_send_json_error( array( 'message' => 'Required fields missing.' ) );
  }

  $inserted = $wpdb->insert( $table, $data );
  if ( $inserted === false ) {
    wp_send_json_error( array( 'message' => 'Database error.' ) );
  }

  wp_send_json_success( array( 'lead_id' => $wpdb->insert_id ) );
}

/* ---------- Enqueue scripts ---------- */

add_action( 'wp_enqueue_scripts', function () {
  wp_enqueue_style( 'habitat-leadgen-style', get_stylesheet_uri(), array(), '1.0' );
  wp_enqueue_style( 'habitat-leadgen-cf7', get_template_directory_uri() . '/assets/css/cf7.css', array( 'habitat-leadgen-style' ), '1.0' );
  // Inline script to pass AJAX URL + nonce to frontend forms
  wp_register_script( 'habitat-leadgen-ajax', false, array(), '1.0', false );
  wp_enqueue_script( 'habitat-leadgen-ajax' );
  wp_add_inline_script(
    'habitat-leadgen-ajax',
    'var hleadAjax = ' . wp_json_encode( array(
      'url'   => admin_url( 'admin-ajax.php' ),
      'nonce' => wp_create_nonce( 'hlead_lead_nonce' ),
    ) ) . ';'
  );
} );

add_action( 'after_setup_theme', function () {
  add_theme_support( 'title-tag' );
  add_theme_support( 'post-thumbnails' );
} );

/* ---------- Settings > Habitat LeadGen (admin page) ---------- */

add_action( 'admin_menu', function () {
  add_options_page( 'BehiveAds', 'BehiveAds', 'manage_options', 'habitat-leadgen', 'hlead_settings_page' );
} );

add_action( 'admin_init', function () {
  register_setting( 'hlead_forms_group', 'hlead_forms' );
  register_setting( 'hlead_options_group', 'hlead_options' );

  // CSV export — must run before any output is sent
  if (
    isset( $_GET['page'], $_GET['hlead_export'] )
    && $_GET['page'] === 'habitat-leadgen'
    && current_user_can( 'manage_options' )
    && check_admin_referer( 'hlead_export' )
  ) {
    global $wpdb;
    $table = $wpdb->prefix . 'leadgen_leads';
    $rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );

    // Clear any buffered output
    if ( ob_get_level() ) { ob_end_clean(); }

    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="behiveads-leads-' . date('Y-m-d') . '.csv"' );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );

    $out = fopen( 'php://output', 'w' );
    if ( ! empty( $rows ) ) {
      fputcsv( $out, array_keys( $rows[0] ) );
      foreach ( $rows as $row ) {
        fputcsv( $out, $row );
      }
    } else {
      fputcsv( $out, array( 'id','page_slug','full_name','phone','email','zip','topic','details','ip_address','created_at' ) );
      fputcsv( $out, array( '—','No leads yet','','','','','','','','' ) );
    }
    fclose( $out );
    exit;
  }
} );

function hlead_settings_page() {
  if ( ! current_user_can( 'manage_options' ) ) { return; }
  require_once get_template_directory() . '/inc/pages.php';
  $pages   = hlead_pages();
  $forms   = get_option( 'hlead_forms', array() );
  $options = get_option( 'hlead_options', array() );
  ?>
  <div class="wrap">
    <h1>BehiveAds</h1>
    <p>Paste your Contact Form 7 shortcodes below (create the forms under <b>Contact &gt; Contact Forms</b> first). Leave a field empty to show the styled demo form instead.</p>
    <form method="post" action="options.php">
      <?php settings_fields( 'hlead_options_group' ); ?>
      <h2>Business details</h2>
      <table class="form-table">
        <tr><th scope="row"><label for="hlead_business">Business name</label></th>
          <td><input id="hlead_business" class="regular-text" type="text" name="hlead_options[business]" value="<?php echo esc_attr( isset( $options['business'] ) ? $options['business'] : '' ); ?>"></td></tr>
        <tr><th scope="row"><label for="hlead_phone">Phone number</label></th>
          <td><input id="hlead_phone" class="regular-text" type="text" name="hlead_options[phone]" value="<?php echo esc_attr( isset( $options['phone'] ) ? $options['phone'] : '' ); ?>" placeholder="+1 (833) 246-4593"></td></tr>
      </table>
      <?php submit_button( 'Save business details' ); ?>
    </form>
    <form method="post" action="options.php">
      <?php settings_fields( 'hlead_forms_group' ); ?>
      <h2>Enquiry form shortcodes (7 pages &times; hero + popup)</h2>
      <table class="form-table">
      <?php foreach ( $pages as $slug => $cfg ) : foreach ( array( 'hero' => 'Hero form', 'popup' => 'Popup form' ) as $kind => $label ) :
        $val = isset( $forms[ $slug ][ $kind ] ) ? $forms[ $slug ][ $kind ] : '';
      ?>
        <tr><th scope="row"><label><?php echo esc_html( $cfg['brand'] . ' — ' . $label ); ?></label></th>
          <td><input class="regular-text code" type="text" name="hlead_forms[<?php echo esc_attr( $slug ); ?>][<?php echo esc_attr( $kind ); ?>]" value="<?php echo esc_attr( $val ); ?>" placeholder='[contact-form-7 id="123" title="..."]'></td></tr>
      <?php endforeach; endforeach; ?>
      </table>
      <?php submit_button( 'Save shortcodes' ); ?>
    </form>
    <h2>How to use</h2>
    <ol>
      <li>Create 7 Pages (<b>Pages &gt; Add New</b>) and assign each its <b>BehiveAds</b> template.</li>
      <li>Create the 14 Contact Form 7 forms and paste the shortcodes above.</li>
      <li>Hero images live in the theme's <code>assets/img/</code> folder — replace them with your own photos any time.</li>
    </ol>

    <?php /* ---------- Leads table ---------- */ ?>
    <h2 style="margin-top:40px">&#128101; Captured Leads</h2>
    <?php
    global $wpdb;
    $table = $wpdb->prefix . 'leadgen_leads';
      if ( ! empty( $rows ) ) {
        fputcsv( $out, array_keys( $rows[0] ) );
        foreach ( $rows as $row ) { fputcsv( $out, $row ); }
      }
      fclose( $out );
      exit;
    // Delete single lead
    if ( isset( $_GET['hlead_delete'] ) && check_admin_referer( 'hlead_delete_' . intval( $_GET['hlead_delete'] ) ) ) {
      $wpdb->delete( $table, array( 'id' => intval( $_GET['hlead_delete'] ) ) );
      echo '<div class="notice notice-success"><p>Lead deleted.</p></div>';
    }
    $page_num  = max( 1, intval( $_GET['leads_page'] ?? 1 ) );
    $per_page  = 20;
    $offset    = ( $page_num - 1 ) * $per_page;
    $total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    $leads     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset ) );
    $export_url = wp_nonce_url( add_query_arg( array( 'page' => 'habitat-leadgen', 'hlead_export' => 1 ), admin_url( 'options-general.php' ) ), 'hlead_export' );
    ?>
    <p>
      Total leads: <strong><?php echo number_format( $total ); ?></strong> &nbsp;|&nbsp;
      Database table: <code><?php echo esc_html( $table ); ?></code> (in <b><?php echo esc_html( DB_NAME ); ?></b>) &nbsp;|&nbsp;
      <a href="<?php echo esc_url( $export_url ); ?>" class="button">&#8595; Export CSV</a>
    </p>
    <?php if ( empty( $leads ) ) : ?>
      <p style="color:#888">No leads yet — submit a demo form on any landing page to see entries here.</p>
    <?php else : ?>
    <table class="widefat striped" style="margin-top:12px">
      <thead>
        <tr>
          <th>#</th><th>Date</th><th>Page</th><th>Name</th>
          <th>Phone</th><th>Email</th><th>ZIP</th><th>Topic</th><th>Details</th><th>IP</th><th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( $leads as $lead ) :
        $del_url = wp_nonce_url(
          add_query_arg( array( 'page' => 'habitat-leadgen', 'hlead_delete' => $lead->id ), admin_url('options-general.php') ),
          'hlead_delete_' . $lead->id
        );
      ?>
        <tr>
          <td><?php echo (int) $lead->id; ?></td>
          <td><?php echo esc_html( $lead->created_at ); ?></td>
          <td><code><?php echo esc_html( $lead->page_slug ); ?></code></td>
          <td><?php echo esc_html( $lead->full_name ); ?></td>
          <td><?php echo esc_html( $lead->phone ); ?></td>
          <td><?php echo esc_html( $lead->email ); ?></td>
          <td><?php echo esc_html( $lead->zip ); ?></td>
          <td><?php echo esc_html( $lead->topic ); ?></td>
          <td><?php echo esc_html( $lead->details ); ?></td>
          <td><?php echo esc_html( $lead->ip_address ); ?></td>
          <td><a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('Delete this lead?')" class="button button-small">Delete</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php
    // Pagination
    $total_pages = ceil( $total / $per_page );
    if ( $total_pages > 1 ) {
      echo '<div style="margin-top:12px">';
      for ( $i = 1; $i <= $total_pages; $i++ ) {
        $url = add_query_arg( array( 'page' => 'habitat-leadgen', 'leads_page' => $i ), admin_url('options-general.php') );
        $style = $i === $page_num ? 'font-weight:bold;text-decoration:none' : '';
        echo '<a href="' . esc_url( $url ) . '" style="' . $style . '" class="button button-small" style="margin-right:4px">' . $i . '</a> ';
      }
      echo '</div>';
    }
    endif; ?>
  </div>
  <?php
}
