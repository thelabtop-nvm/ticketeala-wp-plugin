<?php
/**
 * Plugin Name: Ticketeala Chat
 * Description: Conecta ticketeala.com con el chatbot de IA (alojado en Vercel): muestra la burbuja de chat en todas las páginas, expone los datos de cada evento para que el bot los use, avisa al bot en tiempo real cuando se publica/edita un evento, y añade un panel para revisar las preguntas que el bot no supo responder.
 * Version: 1.0.1
 * Author: Ticketeala
 * Text Domain: ticketeala-chat
 * Update URI: https://github.com/thelabtop-nvm/ticketeala-wp-plugin
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TKCHAT_VERSION', '1.0.1' );

require __DIR__ . '/puc/plugin-update-checker.php';
YahnisElsts\PluginUpdateChecker\v5p7\PucFactory::buildUpdateChecker(
	'https://github.com/thelabtop-nvm/ticketeala-wp-plugin/',
	__FILE__,
	'tkchat-bot'
);

/* ------------------------------------------------------------------ */
/* Settings (Ajustes → Ticketeala Chat)                                */
/* ------------------------------------------------------------------ */

function tkchat_default_options() {
	// No hay secretos aquí a propósito — este archivo vive en un repo público de GitHub
	// (para que la auto-actualización funcione sin necesitar un token). La primera vez,
	// rellena la página de Ajustes con los valores reales y guarda.
	return array(
		'app_url'        => 'https://ticketeala-chatbot.vercel.app',
		'webhook_secret' => '',
		'admin_key'      => '',
	);
}

function tkchat_get_option( $key ) {
	$saved    = get_option( 'tkchat_options', array() );
	$defaults = tkchat_default_options();
	return ( isset( $saved[ $key ] ) && '' !== $saved[ $key ] ) ? $saved[ $key ] : $defaults[ $key ];
}

function tkchat_register_settings() {
	register_setting( 'tkchat_settings', 'tkchat_options', array( 'sanitize_callback' => 'tkchat_sanitize_options' ) );
}
add_action( 'admin_init', 'tkchat_register_settings' );

function tkchat_sanitize_options( $input ) {
	return array(
		'app_url'        => isset( $input['app_url'] ) ? untrailingslashit( esc_url_raw( $input['app_url'] ) ) : '',
		'webhook_secret' => isset( $input['webhook_secret'] ) ? sanitize_text_field( $input['webhook_secret'] ) : '',
		'admin_key'      => isset( $input['admin_key'] ) ? sanitize_text_field( $input['admin_key'] ) : '',
	);
}

function tkchat_admin_menu() {
	add_menu_page( 'Ticketeala Chat', 'Ticketeala Chat', 'manage_options', 'tkchat', 'tkchat_render_gaps_page', 'dashicons-format-chat', 58 );
	add_submenu_page( 'tkchat', 'Preguntas sin responder', 'Preguntas sin responder', 'manage_options', 'tkchat', 'tkchat_render_gaps_page' );
	add_submenu_page( 'tkchat', 'Ajustes', 'Ajustes', 'manage_options', 'tkchat-settings', 'tkchat_render_settings_page' );
}
add_action( 'admin_menu', 'tkchat_admin_menu' );

function tkchat_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	?>
	<div class="wrap">
		<h1>Ajustes — Ticketeala Chat</h1>
		<p>Estos valores ya vienen rellenos con los de tu proyecto en Vercel. Solo tendrías que cambiarlos si rotas algún secreto.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'tkchat_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="tkchat_app_url">URL de la app (Vercel)</label></th>
					<td><input type="url" id="tkchat_app_url" name="tkchat_options[app_url]" value="<?php echo esc_attr( tkchat_get_option( 'app_url' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="tkchat_webhook_secret">Secreto del webhook</label></th>
					<td><input type="text" id="tkchat_webhook_secret" name="tkchat_options[webhook_secret]" value="<?php echo esc_attr( tkchat_get_option( 'webhook_secret' ) ); ?>" class="regular-text">
						<p class="description">Debe coincidir con <code>INGEST_WEBHOOK_SECRET</code> en Vercel.</p></td>
				</tr>
				<tr>
					<th><label for="tkchat_admin_key">Contraseña del panel de administración</label></th>
					<td><input type="text" id="tkchat_admin_key" name="tkchat_options[admin_key]" value="<?php echo esc_attr( tkchat_get_option( 'admin_key' ) ); ?>" class="regular-text">
						<p class="description">Debe coincidir con <code>ADMIN_PASSWORD</code> en Vercel. Se usa para leer/aprobar las preguntas sin responder desde aquí.</p></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* ------------------------------------------------------------------ */
/* Widget: muestra la burbuja de chat en todas las páginas             */
/* ------------------------------------------------------------------ */

function tkchat_print_widget_script() {
	$app_url = tkchat_get_option( 'app_url' );
	if ( ! $app_url ) return;
	echo '<script src="' . esc_url( $app_url . '/widget.js' ) . '" defer></script>' . "\n";
}
add_action( 'wp_footer', 'tkchat_print_widget_script' );

/* ------------------------------------------------------------------ */
/* REST: expone los datos del evento y las FAQs de "Ayuda"             */
/* (autocontenido — no depende de funciones del tema)                  */
/* ------------------------------------------------------------------ */

function tkchat_read_evento_meta( $post_id ) {
	return array(
		'fecha'        => get_post_meta( $post_id, '_evento_fecha', true ),
		'hora'         => get_post_meta( $post_id, '_evento_hora', true ),
		'lugar'        => get_post_meta( $post_id, '_evento_lugar', true ),
		'direccion'    => get_post_meta( $post_id, '_evento_direccion', true ),
		'url_externa'  => get_post_meta( $post_id, '_evento_url_externa', true ),
		'destacado'    => (bool) get_post_meta( $post_id, '_evento_destacado', true ),
		'info'         => get_post_meta( $post_id, '_evento_info', true ),
		'tickets'      => get_post_meta( $post_id, '_evento_tickets', true ) ?: array(),
		'precio_desde' => get_post_meta( $post_id, '_evento_precio_desde', true ),
	);
}

function tkchat_page_is_ayuda( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'page' !== $post->post_type ) return false;
	return 'page-ayuda.php' === get_page_template_slug( $post_id ) || 'ayuda' === $post->post_name;
}

function tkchat_register_rest_fields() {
	if ( post_type_exists( 'evento' ) ) {
		register_rest_field( 'evento', 'ticketeala', array(
			'get_callback' => function ( $post ) {
				return tkchat_read_evento_meta( $post['id'] );
			},
			'schema' => array( 'type' => 'object' ),
		) );
	}

	register_rest_field( 'page', 'ticketeala_faqs', array(
		'get_callback' => function ( $post ) {
			if ( ! tkchat_page_is_ayuda( $post['id'] ) ) return array();
			$faqs = get_post_meta( $post['id'], '_ticketeala_faqs', true );
			return is_array( $faqs ) ? $faqs : array();
		},
		'schema' => array( 'type' => 'array' ),
	) );
}
add_action( 'rest_api_init', 'tkchat_register_rest_fields' );

/* ------------------------------------------------------------------ */
/* Webhook: avisa a la app en Vercel al instante                       */
/* ------------------------------------------------------------------ */

function tkchat_notify( $event, $post_id ) {
	$app_url = tkchat_get_option( 'app_url' );
	$secret  = tkchat_get_option( 'webhook_secret' );
	if ( ! $app_url || ! $secret ) return;
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;

	wp_remote_post( $app_url . '/api/ingest', array(
		'timeout'  => 5,
		'blocking' => false,
		'headers'  => array(
			'Content-Type'        => 'application/json',
			'X-Ticketeala-Secret' => $secret,
		),
		'body' => wp_json_encode( array( 'event' => $event, 'post_id' => $post_id ) ),
	) );
}

add_action( 'save_post_evento', function ( $post_id, $post ) {
	if ( 'publish' !== $post->post_status ) return;
	tkchat_notify( 'evento_saved', $post_id );
}, 20, 2 );

add_action( 'before_delete_post', function ( $post_id ) {
	if ( 'evento' === get_post_type( $post_id ) ) tkchat_notify( 'evento_deleted', $post_id );
} );

add_action( 'save_post_page', function ( $post_id, $post ) {
	if ( 'publish' !== $post->post_status ) return;
	if ( ! tkchat_page_is_ayuda( $post_id ) ) return;
	tkchat_notify( 'ayuda_saved', $post_id );
}, 20, 2 );

/* ------------------------------------------------------------------ */
/* Panel: preguntas sin responder (habla con la app en Vercel)         */
/* ------------------------------------------------------------------ */

function tkchat_api_get( $path ) {
	$app_url = tkchat_get_option( 'app_url' );
	$key     = tkchat_get_option( 'admin_key' );
	$res = wp_remote_get( $app_url . $path, array(
		'timeout' => 15,
		'headers' => array( 'Authorization' => 'Bearer ' . $key ),
	) );
	if ( is_wp_error( $res ) ) return array( 'error' => $res->get_error_message() );
	return json_decode( wp_remote_retrieve_body( $res ), true );
}

function tkchat_api_post( $path, $body ) {
	$app_url = tkchat_get_option( 'app_url' );
	$key     = tkchat_get_option( 'admin_key' );
	$res = wp_remote_post( $app_url . $path, array(
		'timeout' => 15,
		'headers' => array(
			'Authorization' => 'Bearer ' . $key,
			'Content-Type'  => 'application/json',
		),
		'body' => wp_json_encode( $body ),
	) );
	if ( is_wp_error( $res ) ) return array( 'error' => $res->get_error_message() );
	return json_decode( wp_remote_retrieve_body( $res ), true );
}

function tkchat_handle_approve_gap() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No autorizado' );
	check_admin_referer( 'tkchat_approve_gap' );

	$id       = absint( $_POST['gap_id'] ?? 0 );
	$question = sanitize_text_field( wp_unslash( $_POST['question'] ?? '' ) );
	$answer   = sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) );

	if ( $id && $answer ) {
		tkchat_api_post( '/api/admin/gaps', array( 'id' => $id, 'question' => $question, 'answer' => $answer ) );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=tkchat&approved=1' ) );
	exit;
}
add_action( 'admin_post_tkchat_approve_gap', 'tkchat_handle_approve_gap' );

function tkchat_render_gaps_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$data = tkchat_api_get( '/api/admin/gaps' );
	$gaps = is_array( $data ) && isset( $data['gaps'] ) ? $data['gaps'] : array();
	?>
	<div class="wrap">
		<h1>Preguntas sin responder</h1>
		<p>El chatbot registra aquí las preguntas donde no encontró una respuesta con suficiente confianza. Escribe la respuesta correcta y apruébala para añadirla a su base de conocimiento.</p>

		<?php if ( isset( $_GET['approved'] ) ) : ?>
			<div class="notice notice-success"><p>Respuesta añadida al chatbot.</p></div>
		<?php endif; ?>

		<?php if ( isset( $data['error'] ) ) : ?>
			<div class="notice notice-error"><p>No se pudo conectar con la app: <?php echo esc_html( $data['error'] ); ?>. Revisa los ajustes.</p></div>
		<?php elseif ( empty( $gaps ) ) : ?>
			<p>No hay preguntas pendientes. 🎉</p>
		<?php else : ?>
			<?php foreach ( $gaps as $gap ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;padding:16px;margin-top:16px;max-width:700px;">
					<p style="font-weight:600;margin:0 0 4px;"><?php echo esc_html( $gap['question'] ); ?></p>
					<p style="color:#666;font-size:12px;margin:0 0 10px;">
						<?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $gap['created_at'] ) ) ); ?>
						<?php if ( isset( $gap['best_score'] ) && null !== $gap['best_score'] ) : ?>
							· mejor coincidencia: <?php echo esc_html( round( $gap['best_score'] * 100 ) ); ?>%
						<?php endif; ?>
					</p>
					<?php if ( ! empty( $gap['best_answer'] ) ) : ?>
						<p style="font-style:italic;color:#666;">Lo más cercano que encontró: «<?php echo esc_html( $gap['best_answer'] ); ?>»</p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'tkchat_approve_gap' ); ?>
						<input type="hidden" name="action" value="tkchat_approve_gap">
						<input type="hidden" name="gap_id" value="<?php echo esc_attr( $gap['id'] ); ?>">
						<input type="hidden" name="question" value="<?php echo esc_attr( $gap['question'] ); ?>">
						<textarea name="answer" rows="3" class="large-text" placeholder="Escribe la respuesta correcta…" required></textarea>
						<p><button type="submit" class="button button-primary">Aprobar y añadir al bot</button></p>
					</form>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
	<?php
}
