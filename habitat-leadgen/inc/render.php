<?php
/**
 * Renders a full BehiveAds landing page.
 * Domain : behiveads.com
 * Forms  : CF7 shortcodes from Settings > BehiveAds when set,
 *          otherwise a static demo form so the page still looks complete.
 */
require_once __DIR__ . '/pages.php';

function hlead_opt( $key, $default = '' ) {
  $o = get_option( 'hlead_options', array() );
  return isset( $o[ $key ] ) && $o[ $key ] !== '' ? $o[ $key ] : $default;
}

function hlead_forms() {
  $o = get_option( 'hlead_forms', array() );
  return is_array( $o ) ? $o : array();
}

/**
 * Strip non-numeric chars and return a tel: href value.
 */
function hlead_tel( $display ) {
  $tel = preg_replace( '/[^0-9+]/', '', $display );
  return $tel === '' ? '+18888888888' : $tel;
}

/**
 * Format phone for display — NO enforcement.
 * Returns the number exactly as stored/entered.
 */
function hlead_format_phone( $raw ) {
  return $raw; // return as-is — admin decides the format
}

function hlead_static_fields( $cfg, $prefix, $with_details ) {
  $opts = '';
  foreach ( $cfg['dd_opts'] as $o ) {
    $opts .= '<option>' . esc_html( $o ) . '</option>';
  }
  $details = $with_details
    ? '<label for="' . $prefix . '-details">Additional Details</label>'
      . '<textarea id="' . $prefix . '-details" name="details" rows="3" placeholder="Describe what you are seeing and where&hellip;"></textarea>'
    : '';
  return
    '<div class="frow">'
    . '<div><label for="' . $prefix . '-name">Full Name <i>*</i></label><input id="' . $prefix . '-name" required name="name" placeholder="John Smith" autocomplete="name"></div>'
    . '<div><label for="' . $prefix . '-phone">Phone <i>*</i></label><input id="' . $prefix . '-phone" required name="phone" type="tel" placeholder="(888) 888-8888" autocomplete="tel"></div>'
    . '</div>'
    . '<label for="' . $prefix . '-email">Email Address <i>*</i></label><input id="' . $prefix . '-email" required name="email" type="email" placeholder="john@example.com" autocomplete="email">'
    . '<div class="frow">'
    . '<div><label for="' . $prefix . '-zip">ZIP Code <i>*</i></label><input id="' . $prefix . '-zip" required name="zip" inputmode="numeric" placeholder="90210" autocomplete="postal-code"></div>'
    . '<div><label for="' . $prefix . '-topic">' . esc_html( $cfg['dd_label'] ) . ' <i>*</i></label>'
    . '<select id="' . $prefix . '-topic" required name="topic">'
    . '<option value="">' . esc_html( $cfg['dd_placeholder'] ) . '</option>' . $opts . '</select></div>'
    . '</div>'
    . $details;
}

function hlead_form_card( $cfg, $shortcode, $prefix, $with_details, $phone_display, $phone_tel, $in_modal = false ) {
  echo '<div class="form-card' . ( $shortcode !== '' ? ' hab-cf7' : '' ) . '">';
  if ( $in_modal ) {
    echo '<button type="button" class="close" aria-label="Close">&times;</button>';
  }
  // Badge — "FREE" replaced with "NO-COST"
  echo '<span class="free">&#9679; NO-COST QUOTE</span>';
  echo '<h3>' . esc_html( $cfg['form_h3'] ) . '</h3>';
  if ( $shortcode !== '' ) {
    echo do_shortcode( $shortcode );
    echo '<p class="secure">&#128737; Your info is 100% secure. No spam, ever.</p>';
  } else {
    echo '<form class="quote-form" data-slug="' . esc_attr( $cfg['page_slug'] ?? '' ) . '">'
       . hlead_static_fields( $cfg, $prefix, $with_details );
    echo '<button class="btn" type="submit">' . esc_html( $cfg['submit'] ) . ' <span class="arr">&rarr;</span></button>';
    echo '<p class="secure">&#128737; Your info is 100% secure. No spam, ever.</p>';
    echo '<div class="success"><h4>Request Received!</h4><p>' . esc_html( $cfg['success'] ) . '</p></div>';
    echo '</form>';
  }
  echo '<p class="call-line">Or call now: <a href="tel:' . esc_attr( $phone_tel ) . '">' . esc_html( $phone_display ) . '</a></p>';
  echo '</div>';
}

function hlead_render_page( $slug ) {
  $pages = hlead_pages();
  if ( ! isset( $pages[ $slug ] ) ) {
    wp_die( 'Unknown landing page.' );
  }
  $cfg           = $pages[ $slug ];
  $cfg['page_slug'] = $slug;
  $forms         = hlead_forms();
  $hero_sc       = isset( $forms[ $slug ]['hero'] )  ? trim( $forms[ $slug ]['hero'] )  : '';
  $popup_sc      = isset( $forms[ $slug ]['popup'] ) ? trim( $forms[ $slug ]['popup'] ) : '';

  // Phone: per-page override first, then default — exactly as entered, no format change
  $phones        = get_option( 'hlead_phones', array() );
  $page_phone    = isset( $phones[ $slug ] ) && trim( $phones[ $slug ] ) !== '' ? trim( $phones[ $slug ] ) : '';
  $phone_display = $page_phone !== '' ? $page_phone : hlead_opt( 'phone', '(888) 888-8888' );
  if ( trim( $phone_display ) === '' ) { $phone_display = '(888) 888-8888'; }
  $phone_tel     = hlead_tel( $phone_display );
  $phone_tel     = hlead_tel( $phone_display );

  $business      = hlead_opt( 'business', 'BehiveAds' );
  $eyebrow       = implode( ' &middot; ', array_map( 'esc_html', $cfg['dd_opts'] ) );
  $hero_img      = esc_url( get_template_directory_uri() . '/assets/img/' . $cfg['hero_img'] );
  $canonical     = 'https://behiveads.com/' . esc_attr( $slug ) . '/';

  add_filter( 'pre_get_document_title', function () use ( $cfg ) { return $cfg['page_title']; } );
  ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo esc_attr( $cfg['h1'] . ' — ' . $cfg['lead'] ); ?>">
<link rel="canonical" href="<?php echo esc_url( $canonical ); ?>">
<!-- Open Graph -->
<meta property="og:type"        content="website">
<meta property="og:title"       content="<?php echo esc_attr( $cfg['page_title'] ); ?>">
<meta property="og:description" content="<?php echo esc_attr( $cfg['lead'] ); ?>">
<meta property="og:url"         content="<?php echo esc_url( $canonical ); ?>">
<meta property="og:site_name"   content="BehiveAds">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>

<header class="site">
  <div class="wrap">
    <a class="brand" href="https://behiveads.com/"><span class="mark">&#128028;</span> BehiveAds</a>
    <a class="btn" href="tel:<?php echo esc_attr( $phone_tel ); ?>">&#128222; <span><?php echo esc_html( $phone_display ); ?></span></a>
  </div>
</header>

<div class="hero" style="background-image:url('<?php echo $hero_img; ?>')">
  <div class="wrap">
    <div class="hero-grid">
      <div>
        <div class="pill"><?php echo $eyebrow; ?></div>
        <h1><?php echo esc_html( $cfg['h1'] ); ?></h1>
        <p class="lead"><?php echo esc_html( $cfg['lead'] ); ?></p>
        <p class="ticks">&#10003; <b><?php echo esc_html( $cfg['tick1'] ); ?></b> &nbsp;&middot;&nbsp; &#10003; <b><?php echo esc_html( $cfg['tick2'] ); ?></b> &nbsp;&middot;&nbsp; &#10003; <b><?php echo esc_html( $cfg['tick3'] ); ?></b></p>
        <div class="hero-cta">
          <button class="btn open-modal"><?php echo esc_html( $cfg['submit'] ); ?> <span class="arr">&rarr;</span></button>
          <a class="btn-ghost" href="tel:<?php echo esc_attr( $phone_tel ); ?>">&#128222; <?php echo esc_html( $phone_display ); ?></a>
        </div>
        <div class="stats">
          <div class="stat"><b><?php echo esc_html( $cfg['stat1_n'] ); ?></b><span><?php echo esc_html( $cfg['stat1_t'] ); ?></span></div>
          <div class="stat"><b><?php echo esc_html( $cfg['stat2_n'] ); ?></b><span><?php echo esc_html( $cfg['stat2_t'] ); ?></span></div>
          <div class="stat"><b><?php echo esc_html( $cfg['stat3_n'] ); ?></b><span><?php echo esc_html( $cfg['stat3_t'] ); ?></span></div>
        </div>
      </div>
      <?php hlead_form_card( $cfg, $hero_sc, 'hero', false, $phone_display, $phone_tel ); ?>
    </div>
  </div>
</div>

<section class="cream">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow on-light"><?php echo esc_html( $cfg['serv_kicker'] ); ?></div>
      <h2><?php echo esc_html( $cfg['serv_h2'] ); ?></h2>
      <p class="sub"><?php echo esc_html( $cfg['serv_sub'] ); ?></p>
    </div>
    <div class="cards">
      <?php foreach ( $cfg['servs'] as $s ) : ?>
      <div class="card"><div class="icon"><?php echo $s[0]; ?></div><h3><?php echo esc_html( $s[1] ); ?></h3><p><?php echo esc_html( $s[2] ); ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="dark">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow on-dark"><?php echo esc_html( $cfg['proc_kicker'] ); ?></div>
      <h2><?php echo esc_html( $cfg['proc_h2'] ); ?></h2>
      <p class="sub"><?php echo esc_html( $cfg['proc_sub'] ); ?></p>
    </div>
    <div class="steps">
      <?php $i = 1; foreach ( $cfg['steps'] as $st ) : ?>
      <div class="step"><div class="num"><?php echo $i++; ?></div><h3><?php echo esc_html( $st[0] ); ?></h3><p><?php echo esc_html( $st[1] ); ?></p></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="cream">
  <div class="wrap">
    <div class="guarantee">
      <div class="eyebrow on-light"><?php echo esc_html( $cfg['guar_kicker'] ); ?></div>
      <h2><?php echo esc_html( $cfg['guar_h2'] ); ?></h2>
      <p class="big"><?php echo esc_html( $cfg['guar_text'] ); ?></p>
    </div>
  </div>
</section>

<section class="dark">
  <div class="wrap">
    <div class="sec-head">
      <div class="eyebrow on-dark">Testimonials</div>
      <h2>What Customers Say</h2>
      <p class="sub">[Replace these three slots with your own customer reviews.]</p>
    </div>
    <div class="quotes">
      <div class="quote"><div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><p>"[Review 1 — e.g. what the problem was, and the result after switching.]"</p><span>&mdash; [Customer type / area]</span></div>
      <div class="quote"><div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><p>"[Review 2 — e.g. how fast the quote arrived, and how clearly the plan was explained.]"</p><span>&mdash; [Customer type / area]</span></div>
      <div class="quote"><div class="stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div><p>"[Review 3 — e.g. a follow-up experience or savings on an annual plan.]"</p><span>&mdash; [Customer type / area]</span></div>
    </div>
  </div>
</section>

<section class="cta">
  <div class="wrap">
    <div class="eyebrow on-dark" style="justify-content:center"><?php echo esc_html( $cfg['tick3'] ); ?></div>
    <h2><?php echo esc_html( $cfg['cta_h2'] ); ?></h2>
    <p><?php echo esc_html( $cfg['cta_p'] ); ?></p>
    <button class="btn open-modal"><?php echo esc_html( $cfg['submit'] ); ?> <span class="arr">&rarr;</span></button>
  </div>
</section>

<footer>
  <div class="wrap">
    <span><b>BehiveAds</b> &mdash; <?php echo esc_html( $business ); ?></span>
    <span>Call: <a href="tel:<?php echo esc_attr( $phone_tel ); ?>"><?php echo esc_html( $phone_display ); ?></a></span>
    <span><a href="https://behiveads.com/" style="color:var(--orange);text-decoration:none;font-weight:700">behiveads.com</a></span>
  </div>
  <div class="wrap disclaimer-wrap">
    <p class="disclaimer">
      <strong>Disclaimer:</strong> Behive Ads is a service to assist homeowners in connecting with local service providers.
      All contractors/providers are independent, and Behive Ads does not warrant or guarantee any work performed.
      It is the responsibility of the homeowner to verify that the hired contractor furnishes the necessary license
      and insurance required for the work being performed. All persons depicted in a photo or video are actors or
      models and not contractors listed on Behive Ads.
    </p>
  </div>
</footer>

<div class="modal" id="quoteModal" aria-hidden="true">
  <?php hlead_form_card( $cfg, $popup_sc, 'modal', true, $phone_display, $phone_tel, true ); ?>
</div>

<?php wp_footer(); ?>
<script>
  var modal = document.getElementById('quoteModal');
  function openModal(){ modal.classList.add('open'); modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); }
  document.querySelectorAll('.open-modal').forEach(function(b){ b.addEventListener('click', openModal); });
  var closeBtn = modal.querySelector('.close');
  if (closeBtn) { closeBtn.addEventListener('click', closeModal); }
  modal.addEventListener('click', function(e){ if(e.target === modal) closeModal(); });
  document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeModal(); });

  /* ---------- Demo form AJAX submit — saves lead to wp_leadgen_leads ---------- */
  document.querySelectorAll('.quote-form').forEach(function(f){
    f.addEventListener('submit', function(e){
      e.preventDefault();
      var btn = f.querySelector('button[type="submit"]');
      var origText = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = 'Sending&hellip;'; }

      var data = new FormData(f);
      data.append('action', 'hlead_save_lead');
      data.append('page_slug', f.dataset.slug || '');
      data.append('nonce', (typeof hleadAjax !== 'undefined') ? hleadAjax.nonce : '');

      var ajaxUrl = (typeof hleadAjax !== 'undefined') ? hleadAjax.url : '/wp-admin/admin-ajax.php';

      fetch(ajaxUrl, { method: 'POST', body: data })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res.success) {
            f.querySelector('.success').style.display = 'block';
            f.querySelectorAll('input,select,textarea').forEach(function(el){ el.value = ''; });
          } else {
            alert('Sorry, there was an error. Please try again.');
          }
        })
        .catch(function(){
          f.querySelector('.success').style.display = 'block';
        })
        .finally(function(){
          if (btn) { btn.disabled = false; btn.innerHTML = origText; }
        });
    });
  });
</script>
</body>
</html>
  <?php
}
