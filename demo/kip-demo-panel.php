<?php
/**
 * Plugin Name: Kipphard Demo — Live Design Panel
 * Description: DEMO ONLY. A floating control panel (bottom-left) to try the kip-ui
 *              design live — preset, accent, layout, scheme (client-side) plus any
 *              plugin-specific server settings declared in the `kip_demo_panel_config`
 *              option (e.g. wishlist button style). Shipped only into the WordPress
 *              Playground demo (a mu-plugin the blueprint fetches), never in a plugin.
 *
 * STANDARD for future demos: in the demo blueprint, `mkdir` /wordpress/wp-content/mu-plugins,
 * `writeFile` this file there (resource = the plugin repo's raw demo/kip-demo-panel.php), and in
 * runPHP set update_option('kip_demo_panel_config', array(
 *   'option'  => '<plugin>_settings',   // optional: settings option for server toggles
 *   'layout'  => true|false,            // show the client-side layout control (surface has [data-layout])
 *   'toggles' => array( array( 'label'=>'…','key'=>'<setting_key>','options'=>array(
 *                  array('val'=>'…','label'=>'…'), … ) ), … ),  // optional server toggles
 * )).
 *
 * @package KipphardDemo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generic server-toggle handler: writes a whitelisted key→value into the configured
 * settings option, then the panel reloads. Whitelist comes from the config so only
 * declared settings/values can be written. Demo runs as admin.
 */
add_action(
	'wp_ajax_kip_demo_set',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( '', 403 );
		}
		check_ajax_referer( 'kip_demo' );
		$cfg    = (array) get_option( 'kip_demo_panel_config', array() );
		$option = isset( $cfg['option'] ) ? (string) $cfg['option'] : '';
		$key    = isset( $_POST['key'] ) ? sanitize_key( $_POST['key'] ) : '';
		$val    = isset( $_POST['value'] ) ? sanitize_text_field( wp_unslash( $_POST['value'] ) ) : '';

		$ok = false;
		if ( $option && ! empty( $cfg['toggles'] ) ) {
			foreach ( (array) $cfg['toggles'] as $t ) {
				if ( isset( $t['key'] ) && $t['key'] === $key && ! empty( $t['options'] ) ) {
					foreach ( (array) $t['options'] as $o ) {
						if ( isset( $o['val'] ) && (string) $o['val'] === $val ) {
							$ok = true;
							break 2;
						}
					}
				}
			}
		}
		if ( ! $ok ) {
			wp_send_json_error( '', 400 );
		}

		$s         = (array) get_option( $option, array() );
		$s[ $key ] = $val;
		update_option( $option, $s );
		wp_send_json_success();
	}
);

add_action(
	'wp_footer',
	static function () {
		if ( is_admin() ) {
			return;
		}
		$cfg         = (array) get_option( 'kip_demo_panel_config', array() );
		$show_layout = ! empty( $cfg['layout'] );
		$toggles     = isset( $cfg['toggles'] ) ? (array) $cfg['toggles'] : array();
		$option      = isset( $cfg['option'] ) ? (string) $cfg['option'] : '';
		$opt_vals    = $option ? (array) get_option( $option, array() ) : array();
		$srv         = array();
		foreach ( $toggles as $t ) {
			if ( isset( $t['key'] ) ) {
				$srv[ $t['key'] ] = isset( $opt_vals[ $t['key'] ] ) ? (string) $opt_vals[ $t['key'] ] : '';
			}
		}
		?>
<style id="kip-demo-panel-css">
.kdp-fab,.kdp{position:fixed;left:20px;bottom:20px;z-index:100000;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
.kdp-fab{display:inline-flex;align-items:center;gap:8px;padding:11px 16px;border:0;border-radius:999px;background:#16181d;color:#fff;font-size:14px;font-weight:600;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.28);transition:transform .15s ease}
.kdp-fab:hover{transform:translateY(-2px)}
.kdp{width:300px;max-width:calc(100vw - 40px);max-height:calc(100vh - 40px);overflow:auto;background:#16181d;color:#e7eaee;border:1px solid #2a2e37;border-radius:16px;box-shadow:0 24px 60px -12px rgba(0,0,0,.55);padding:16px 18px 18px;display:none}
.kdp.is-open{display:block}
.kdp__head{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.kdp__title{font-size:14px;font-weight:700}
.kdp__close{background:none;border:0;color:#98a2b3;font-size:20px;line-height:1;cursor:pointer}
.kdp__group{margin-top:14px}
.kdp__label{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#98a2b3;margin-bottom:7px}
.kdp__row{display:flex;flex-wrap:wrap;gap:6px}
.kdp__opt{flex:1 1 auto;min-width:0;padding:7px 10px;border:1px solid #2f3543;border-radius:9px;background:#1f242d;color:#cdd3dc;font-size:12.5px;font-weight:600;cursor:pointer;transition:all .12s ease;text-align:center}
.kdp__opt:hover{border-color:#475067}
.kdp__opt.is-active{background:#fff;color:#16181d;border-color:#fff}
.kdp__swatches{display:flex;gap:8px}
.kdp__sw{width:26px;height:26px;border-radius:50%;border:2px solid transparent;cursor:pointer;padding:0;transition:transform .12s ease}
.kdp__sw:hover{transform:scale(1.12)}
.kdp__sw.is-active{border-color:#fff;box-shadow:0 0 0 2px #16181d,0 0 0 4px #fff}
.kdp__note{margin-top:16px;font-size:11px;color:#667085;text-align:center}
@media (prefers-reduced-motion:reduce){.kdp-fab,.kdp__sw,.kdp__opt{transition:none}}
</style>

<button type="button" class="kdp-fab" id="kdp-fab" aria-expanded="false" aria-controls="kdp-panel">
	<svg width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3a9 9 0 0 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.6-1.3-.3-.4-.5-.8-.5-1.2 0-.9.7-1.5 1.6-1.5H16a5 5 0 0 0 5-5c0-3.9-4-7-9-7Z" stroke="currentColor" stroke-width="1.7"/><circle cx="7.5" cy="11.5" r="1.2" fill="currentColor"/><circle cx="12" cy="8" r="1.2" fill="currentColor"/><circle cx="16.5" cy="11.5" r="1.2" fill="currentColor"/></svg>
	<span>Design</span>
</button>

<div class="kdp" id="kdp-panel" role="dialog" aria-label="Live design controls">
	<div class="kdp__head">
		<span class="kdp__title">Customize the look</span>
		<button type="button" class="kdp__close" id="kdp-close" aria-label="Close">&times;</button>
	</div>
	<div class="kdp__group">
		<div class="kdp__label">Preset</div>
		<div class="kdp__row" data-kdp="preset">
			<button class="kdp__opt" data-val="theme">Theme</button>
			<button class="kdp__opt" data-val="soft">Soft</button>
			<button class="kdp__opt" data-val="bold">Bold</button>
			<button class="kdp__opt" data-val="minimal">Minimal</button>
		</div>
	</div>
	<div class="kdp__group">
		<div class="kdp__label">Accent</div>
		<div class="kdp__swatches" data-kdp="accent">
			<button class="kdp__sw" data-val="#f0834e" style="background:#f0834e" aria-label="Orange"></button>
			<button class="kdp__sw" data-val="#2563eb" style="background:#2563eb" aria-label="Blue"></button>
			<button class="kdp__sw" data-val="#db2777" style="background:#db2777" aria-label="Pink"></button>
			<button class="kdp__sw" data-val="#16a34a" style="background:#16a34a" aria-label="Green"></button>
			<button class="kdp__sw" data-val="#7c3aed" style="background:#7c3aed" aria-label="Violet"></button>
			<button class="kdp__sw" data-val="#0ea5e9" style="background:#0ea5e9" aria-label="Sky"></button>
		</div>
	</div>
	<?php if ( $show_layout ) : ?>
	<div class="kdp__group">
		<div class="kdp__label">Layout</div>
		<div class="kdp__row" data-kdp="layout">
			<button class="kdp__opt" data-val="grid">Grid</button>
			<button class="kdp__opt" data-val="list">List</button>
			<button class="kdp__opt" data-val="table">Table</button>
		</div>
	</div>
	<?php endif; ?>
	<div class="kdp__group">
		<div class="kdp__label">Scheme</div>
		<div class="kdp__row" data-kdp="scheme">
			<button class="kdp__opt" data-val="auto">Auto</button>
			<button class="kdp__opt" data-val="light">Light</button>
			<button class="kdp__opt" data-val="dark">Dark</button>
		</div>
	</div>
	<?php foreach ( $toggles as $t ) : ?>
		<?php if ( empty( $t['key'] ) || empty( $t['options'] ) ) { continue; } ?>
	<div class="kdp__group">
		<div class="kdp__label"><?php echo esc_html( isset( $t['label'] ) ? $t['label'] : $t['key'] ); ?></div>
		<div class="kdp__row" data-kdp-srv="<?php echo esc_attr( $t['key'] ); ?>">
			<?php foreach ( (array) $t['options'] as $o ) : ?>
				<button class="kdp__opt" data-val="<?php echo esc_attr( $o['val'] ); ?>"><?php echo esc_html( isset( $o['label'] ) ? $o['label'] : $o['val'] ); ?></button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endforeach; ?>
	<div class="kdp__note">Live preview · demo only</div>
</div>

<script id="kip-demo-panel-js">
(function(){
	var KDP={ajax:<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,nonce:<?php echo wp_json_encode( wp_create_nonce( 'kip_demo' ) ); ?>,srv:<?php echo wp_json_encode( (object) $srv ); ?>};
	var KEY='kipDemoDesign';
	var state={preset:'soft',accent:'#f0834e',layout:'grid',scheme:'auto'};
	try{var s=JSON.parse(sessionStorage.getItem(KEY)||'{}');for(var k in s){if(s[k])state[k]=s[k];}}catch(e){}

	function apply(){
		document.querySelectorAll('.kip-ui').forEach(function(el){
			el.setAttribute('data-kip-preset',state.preset);
			el.setAttribute('data-kip-scheme',state.scheme);
			el.style.setProperty('--kip-accent',state.accent);
			el.style.setProperty('--kip-accent-hover',state.accent);
		});
		document.querySelectorAll('[data-layout]').forEach(function(el){el.setAttribute('data-layout',state.layout);});
		sync();
	}
	function sync(){
		document.querySelectorAll('[data-kdp] .kdp__opt,[data-kdp] .kdp__sw').forEach(function(b){
			var g=b.parentNode.getAttribute('data-kdp');
			b.classList.toggle('is-active',b.getAttribute('data-val')===state[g]);
		});
		document.querySelectorAll('[data-kdp-srv]').forEach(function(row){
			var key=row.getAttribute('data-kdp-srv');
			row.querySelectorAll('.kdp__opt').forEach(function(b){b.classList.toggle('is-active',b.getAttribute('data-val')===KDP.srv[key]);});
		});
	}
	function save(){try{sessionStorage.setItem(KEY,JSON.stringify(state));}catch(e){}}

	document.addEventListener('click',function(e){
		var srv=e.target.closest('[data-kdp-srv] .kdp__opt');
		if(srv){var key=srv.parentNode.getAttribute('data-kdp-srv');var v=srv.getAttribute('data-val');
			var body='action=kip_demo_set&key='+encodeURIComponent(key)+'&value='+encodeURIComponent(v)+'&_ajax_nonce='+encodeURIComponent(KDP.nonce);
			fetch(KDP.ajax,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body}).then(function(){location.reload();});return;}
		var opt=e.target.closest('[data-kdp] .kdp__opt,[data-kdp] .kdp__sw');
		if(opt){var g=opt.parentNode.getAttribute('data-kdp');state[g]=opt.getAttribute('data-val');save();apply();return;}
		if(e.target.closest('#kdp-fab')){toggle();return;}
		if(e.target.closest('#kdp-close')){toggle(false);return;}
	});
	function toggle(force){
		var p=document.getElementById('kdp-panel'),f=document.getElementById('kdp-fab');
		var open=(force===undefined)?!p.classList.contains('is-open'):force;
		p.classList.toggle('is-open',open);f.setAttribute('aria-expanded',open?'true':'false');
	}
	function init(){apply();}
	if(document.readyState!=='loading'){init();}else{document.addEventListener('DOMContentLoaded',init);}
})();
</script>
		<?php
	}
);
