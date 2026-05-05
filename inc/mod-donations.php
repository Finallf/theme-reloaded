<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Module: Donations - Pix, GitHub Sponsors e Paypal
 *******************************************************************************/

/*******************************************************************************
 * Módulo de Apoio                                                 - (Doações) *
 *******************************************************************************/
function rd_render_support_block() {
    $github      = rd_get_option('github_sponsors');
    $pix_key     = rd_get_option('pix_chave');
    $pix_img     = rd_get_option('pix_qrcode');
    $pix_url     = rd_get_option('pix_url'); // O novo campo de link
    $paypal_url  = rd_get_option('paypal_url');
    $paypal_qr   = rd_get_option('paypal_qrcode');

    if ( empty($github) && empty($pix_key) && empty($pix_img) && empty($paypal_url) ) {
        return;
    }

    echo '<section class="rd-support-block">';
    echo '  <h3 class="rd-support-title">Apoie o Projeto</h3>';
    echo '  <div class="rd-support-content">';

    // GitHub Sponsors
    if ( $github ) {
        echo '<a href="' . esc_url( $github ) . '" target="_blank" class="rd-qr-link">';
        echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/github-sponsor.svg' ) . '" alt="GitHub Sponsors">';
        echo '</a>';
    }

    // PIX (Nacional)
    if ( $pix_key || $pix_img ) {
        echo '<div class="rd-support-subitem">';
        
        // Imagem do PIX com Link Dinâmico
        if ( $pix_img ) {
            if ( $pix_url ) {
                
                echo '<a href="' . esc_url( $pix_url ) . '" target="_blank" class="rd-qr-link">';
                echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/logo-pix.svg' ) . '" alt="PIX Logo">';
                echo '  <img src="' . esc_url( $pix_img ) . '" class="rd-qr-img" alt="QR Code PIX">';
                echo '  <small>(Clique ou Escaneie para doar)</small>';
                echo '</a>';
            } else {
                echo '<img src="' . esc_url( $pix_img ) . '" class="rd-qr-img" alt="QR Code PIX">';
            }
        }

        // Chave PIX com Botão de Copiar Moderno
        if ( $pix_key ) {
            echo '<div class="rd-pix-copy-area">';
            echo '  <span class="rd-key-label">Chave PIX:</span>';
            // O botão carrega a chave real no onclick e aciona o script que está no fim do arquivo
            echo '  <button class="rd-copy-btn" onclick="rdCopyPix(this, \'' . esc_js( $pix_key ) . '\')" aria-label="Copiar chave PIX" title="Clique para copiar">';
            echo '    <span class="rd-copy-text">' . esc_html( $pix_key ) . '</span>';
            echo '    <i class="fas fa-copy"></i>';
            echo '  </button>';
            echo '</div>';
        }
        
        echo '</div>';
    }

    // PayPal (Internacional)
    if ( $paypal_url || $paypal_qr ) {
        echo '<div class="rd-support-subitem rd-paypal-container">';
        
        if ( $paypal_qr ) {
            if ( $paypal_url ) {
                echo '<a href="' . esc_url( $paypal_url ) . '" target="_blank" class="rd-qr-link">';
                echo '  <img src="' . esc_url( get_template_directory_uri() . '/assets/img/logo-paypal.svg' ) . '" alt="PayPal Logo">';
                echo '  <img src="' . esc_url( $paypal_qr ) . '" class="rd-qr-img" alt="PayPal Donate">';
                echo '  <small>(Clique ou Escaneie para doar)</small>';
                echo '</a>';
            } else {
                echo '<img src="' . esc_url( $paypal_qr ) . '" class="rd-qr-img" alt="PayPal QR">';
            }
        } elseif ( $paypal_url ) {
            echo '<a href="' . esc_url( $paypal_url ) . '" target="_blank" class="rd-btn-support rd-paypal">Doar via PayPal</a>';
        }

        echo '</div>';
    }

    echo '  </div>';
    echo '</section>';

    // Pequeno Script Injetado para gerenciar o "Copia e Cola" sem depender de bibliotecas
    if ( $pix_key ) {
        echo "<script>
        function rdCopyPix(btn, pixKey) {
            navigator.clipboard.writeText(pixKey).then(function() {
                let textSpan = btn.querySelector('.rd-copy-text');
                let originalText = textSpan.innerText;
                textSpan.innerText = 'Chave Copiada!';
                btn.classList.add('copied');
                
                // Retorna ao estado original após 2 segundos
                setTimeout(function() {
                    textSpan.innerText = originalText;
                    btn.classList.remove('copied');
                }, 2000);
            }).catch(function(err) {
                console.error('Erro ao copiar: ', err);
            });
        }
        </script>";
    }
}