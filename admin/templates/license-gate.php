<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * License gate — full-screen popup shown on every plugin page until a
 * license key is activated. Guides the user to the frontend website to
 * create an account and generate a free license key.
 */

$sas_frontend = untrailingslashit( SAS_FRONTEND_URL );

$gate_error   = get_transient( 'sas_license_error' );   delete_transient( 'sas_license_error' );
$gate_success = get_transient( 'sas_license_success' ); delete_transient( 'sas_license_success' );
?>
<div class="sas-wrap">
    <div class="sas-gate-overlay">
        <div class="sas-gate-modal">

            <div class="sas-gate-icon">🔑</div>

            <h1 class="sas-gate-title"><?php esc_html_e( 'Activate Your Free License', 'social-auto-scheduler' ); ?></h1>
            <p class="sas-gate-sub">
                <?php esc_html_e( 'Social Auto Scheduler needs a license key to connect this website. Generate a FREE license key in under a minute — one key per website.', 'social-auto-scheduler' ); ?>
            </p>

            <?php if ( $gate_error ) : ?>
                <div class="sas-gate-notice sas-gate-notice--error"><?php echo esc_html( $gate_error ); ?></div>
            <?php endif; ?>
            <?php if ( $gate_success ) : ?>
                <div class="sas-gate-notice sas-gate-notice--success"><?php echo esc_html( $gate_success ); ?></div>
            <?php endif; ?>

            <!-- Steps -->
            <div class="sas-gate-steps">
                <div class="sas-gate-step">
                    <span class="sas-gate-step-num">1</span>
                    <div>
                        <strong><?php esc_html_e( 'Create a free account', 'social-auto-scheduler' ); ?></strong>
                        <p><?php esc_html_e( 'Sign up on our website (or log in if you already have an account).', 'social-auto-scheduler' ); ?></p>
                    </div>
                </div>
                <div class="sas-gate-step">
                    <span class="sas-gate-step-num">2</span>
                    <div>
                        <strong><?php esc_html_e( 'Generate a license key', 'social-auto-scheduler' ); ?></strong>
                        <p><?php esc_html_e( 'Go to Dashboard → Licenses and click "Generate Free License".', 'social-auto-scheduler' ); ?></p>
                    </div>
                </div>
                <div class="sas-gate-step">
                    <span class="sas-gate-step-num">3</span>
                    <div>
                        <strong><?php esc_html_e( 'Paste the key below', 'social-auto-scheduler' ); ?></strong>
                        <p><?php esc_html_e( 'Activate it and start scheduling videos to YouTube & Instagram.', 'social-auto-scheduler' ); ?></p>
                    </div>
                </div>
            </div>

            <!-- Account buttons -->
            <div class="sas-gate-actions">
                <a href="<?php echo esc_url( $sas_frontend . '/register' ); ?>" target="_blank" rel="noopener noreferrer"
                   class="sas-gate-btn sas-gate-btn--primary">
                    <?php esc_html_e( 'Create Free Account', 'social-auto-scheduler' ); ?> ↗
                </a>
                <a href="<?php echo esc_url( $sas_frontend . '/login' ); ?>" target="_blank" rel="noopener noreferrer"
                   class="sas-gate-btn sas-gate-btn--ghost">
                    <?php esc_html_e( 'Log In', 'social-auto-scheduler' ); ?> ↗
                </a>
                <a href="<?php echo esc_url( $sas_frontend . '/licenses' ); ?>" target="_blank" rel="noopener noreferrer"
                   class="sas-gate-btn sas-gate-btn--ghost">
                    <?php esc_html_e( 'My Licenses', 'social-auto-scheduler' ); ?> ↗
                </a>
            </div>

            <div class="sas-gate-divider"><span><?php esc_html_e( 'then activate', 'social-auto-scheduler' ); ?></span></div>

            <!-- Activation form -->
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sas-gate-form">
                <?php wp_nonce_field( 'sas_activate_license' ); ?>
                <input type="hidden" name="action" value="sas_activate_license">
                <input type="text" name="sas_license_token" class="sas-gate-input"
                       placeholder="<?php esc_attr_e( 'Paste your license key — XXXX-XXXX-XXXX-XXXX', 'social-auto-scheduler' ); ?>"
                       required autocomplete="off">
                <button type="submit" class="sas-gate-btn sas-gate-btn--primary sas-gate-btn--submit">
                    <?php esc_html_e( 'Activate License', 'social-auto-scheduler' ); ?>
                </button>
            </form>

            <p class="sas-gate-foot">
                <?php
                printf(
                    /* translators: %s: link to the frontend website */
                    esc_html__( 'Need help? Visit %s for guides and support.', 'social-auto-scheduler' ),
                    '<a href="' . esc_url( $sas_frontend ) . '" target="_blank" rel="noopener noreferrer">soulitam.com</a>'
                );
                ?>
            </p>
        </div>
    </div>
</div>

<style>
    /*
     * Cover only the content area — keep the WP admin bar (top, 32px) and
     * admin menu (left, 160px / 36px folded) visible and clickable.
     * z-index stays below #adminmenuwrap (9990) and #wpadminbar (99999).
     */
    .sas-gate-overlay {
        position: fixed;
        top: 32px;
        left: 160px;
        right: 0;
        bottom: 0;
        z-index: 9980;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(99,102,241,0.25), transparent),
                    rgba(8, 10, 24, 0.92);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        overflow-y: auto;
    }
    /* Collapsed admin menu */
    body.folded .sas-gate-overlay { left: 36px; }
    /* Auto-folded on medium screens */
    @media (max-width: 960px) {
        .sas-gate-overlay { left: 36px; }
    }
    /* Mobile: menu is hidden, admin bar grows to 46px */
    @media (max-width: 782px) {
        .sas-gate-overlay { left: 0; top: 46px; }
    }
    .sas-gate-modal {
        width: 100%;
        max-width: 560px;
        margin: auto;
        padding: 40px 44px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.10);
        box-shadow: 0 32px 80px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(24px);
        -webkit-backdrop-filter: blur(24px);
        text-align: center;
        color: #e5e7eb;
    }
    .sas-gate-icon {
        width: 64px; height: 64px;
        margin: 0 auto 18px;
        display: flex; align-items: center; justify-content: center;
        font-size: 30px;
        border-radius: 16px;
        background: rgba(99,102,241,0.15);
        border: 1px solid rgba(99,102,241,0.35);
        box-shadow: 0 0 32px rgba(99,102,241,0.35);
    }
    .sas-gate-title {
        margin: 0 0 10px;
        font-size: 26px;
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.02em;
    }
    .sas-gate-sub {
        margin: 0 auto 22px;
        max-width: 420px;
        font-size: 14px;
        line-height: 1.6;
        color: rgba(255,255,255,0.55);
    }
    .sas-gate-notice {
        margin: 0 0 18px;
        padding: 10px 14px;
        border-radius: 10px;
        font-size: 13px;
        text-align: left;
    }
    .sas-gate-notice--error   { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.35); color: #fca5a5; }
    .sas-gate-notice--success { background: rgba(34,197,94,0.12); border: 1px solid rgba(34,197,94,0.35); color: #86efac; }

    .sas-gate-steps {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 24px;
        text-align: left;
    }
    .sas-gate-step {
        display: flex;
        gap: 14px;
        align-items: flex-start;
        padding: 12px 16px;
        border-radius: 12px;
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.07);
    }
    .sas-gate-step-num {
        flex-shrink: 0;
        width: 26px; height: 26px;
        display: flex; align-items: center; justify-content: center;
        border-radius: 50%;
        font-size: 12px; font-weight: 700;
        color: #c7d2fe;
        background: rgba(99,102,241,0.18);
        border: 1px solid rgba(99,102,241,0.4);
    }
    .sas-gate-step strong { display: block; color: #fff; font-size: 13.5px; margin-bottom: 2px; }
    .sas-gate-step p { margin: 0; font-size: 12.5px; color: rgba(255,255,255,0.5); line-height: 1.5; }

    .sas-gate-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }
    .sas-gate-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        border: 1px solid transparent;
        transition: all .18s ease;
    }
    .sas-gate-btn--primary {
        background: #6366f1;
        color: #fff !important;
        box-shadow: 0 0 20px rgba(99,102,241,0.45);
    }
    .sas-gate-btn--primary:hover { background: #575af0; box-shadow: 0 0 30px rgba(99,102,241,0.6); color: #fff; }
    .sas-gate-btn--ghost {
        background: rgba(255,255,255,0.05);
        border-color: rgba(255,255,255,0.12);
        color: rgba(255,255,255,0.85) !important;
    }
    .sas-gate-btn--ghost:hover { background: rgba(255,255,255,0.1); color: #fff; }

    .sas-gate-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 20px 0;
        color: rgba(255,255,255,0.35);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.12em;
    }
    .sas-gate-divider::before,
    .sas-gate-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: rgba(255,255,255,0.1);
    }

    .sas-gate-form { display: flex; gap: 10px; }
    .sas-gate-input {
        flex: 1;
        padding: 11px 14px;
        border-radius: 10px;
        font-size: 13.5px;
        color: #fff;
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(255,255,255,0.14) !important;
        outline: none;
        transition: border-color .18s ease;
    }
    .sas-gate-input::placeholder { color: rgba(255,255,255,0.35); }
    .sas-gate-input:focus { border-color: rgba(99,102,241,0.6) !important; box-shadow: 0 0 0 3px rgba(99,102,241,0.2); }
    .sas-gate-btn--submit { flex-shrink: 0; }

    .sas-gate-foot { margin: 18px 0 0; font-size: 12px; color: rgba(255,255,255,0.4); }
    .sas-gate-foot a { color: #a5b4fc; text-decoration: none; }
    .sas-gate-foot a:hover { text-decoration: underline; }

    @media (max-width: 600px) {
        .sas-gate-modal { padding: 28px 22px; }
        .sas-gate-form { flex-direction: column; }
    }
</style>
