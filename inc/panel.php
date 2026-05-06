<?php
defined('ABSPATH') || exit;
/*******************************************************************************
 * Painel de Opções Avançado - ReloadeD
 * Organizado por Abas (Tabs) para LGPD, Performance, SEO e Integrações.
 *******************************************************************************/

/*******************************************************************************
 * Configurações padrão na ativação do tema                      - (Hardcoded) *
 *******************************************************************************/
function rd_set_default_options() {
    // Verifica se as configurações já existem no banco
    $options = get_option('rd_settings');

    // Se for falso, significa que é a primeira vez que o tema está sendo ativado
    if ( false === $options ) {
        $defaults = array(
            'back_to_top'           => 1, // Ligado
            'enable_top_bar'        => 1,
            'image_resizing'        => 1,
            'enable_thumb_control'  => 1,
            'jpeg_quality'          => 90, // Valor padrão
            'comment_a11y'          => 1,
            'excerpt_text'          => '',
            'comments_separator'    => '',
            'date_format'           => '',
            'enable_views_tracking' => 1,

            'enable_lgpd'           => 1,
            'lgpd_text'             => 'Nós usamos cookies e tecnologias semelhantes para melhorar a sua experiência. Ao continuar navegando, você concorda com a nossa <a href="/politica-de-privacidade">Política de Privacidade</a>.',

            'ga_id'                 => '',
            'discord_widget'        => 1,
            'discord_id'            => '',

            'markdown_enabled'      => 1,
            'prism_js'              => 1,
            'disable_emojis'        => 1,
            'hide_wp_ver'           => 1,
            'facades_enabled'       => 1,
            'disable_gutenberg_css' => 1,

            'social_discord'        => '',
            'social_telegram'       => '',
            'social_youtube'        => '',
            'social_instagram'      => '',
            'social_steam'          => '',
            'social_twitter'        => '',
            'social_facebook'       => '',
            'social_whatsapp'       => '',

            'enable_open_graph'     => 1,
            'og_fallback_image'     => '',

            'github_sponsors'       => '',
            'paypal_url'            => '',
            'paypal_qrcode'         => '',
            'pix_url'               => '',
            'pix_qrcode'            => '',
            'pix_chave'             => '',

            'ad_global'             => '',
            'ad_topo_desktop'       => '',
            'ad_topo_mobile'        => '',
            'ad_sidebar_top'        => '',
            'ad_sidebar_sticky'     => '',

            'maintenance_mode'      => 0,
            'maintenance_pass'      => '',
            'maintenance_text'      => '',
        );
        // Salva os padrões no banco de dados
        update_option('rd_settings', $defaults);
    }
}
add_action('after_switch_theme', 'rd_set_default_options');

/***********************************************************************************
 * Busca uma opção do painel de forma segura (Defensive Programming) - (Hardcoded) *
 ***********************************************************************************/
function rd_get_option( $key, $default = false ) {
    $opt = get_option('rd_settings');

    if ( ! isset( $opt ) || ! isset( $opt[$key] ) ) {
        return $default;
    }

    return $opt[$key];
}

/*******************************************************************************
 * Cria o Menu no Painel
 *******************************************************************************/
function rd_add_admin_menu() {
    add_menu_page(
        'ReloadeD Opções',
        'ReloadeD',
        'manage_options',
        'rd_options',
        'rd_options_render',
        get_template_directory_uri() . '/assets/img/logo-reloaded-20x20.webp',
        3
    );
}
add_action('admin_menu', 'rd_add_admin_menu');

/*******************************************************************************
 * Renderiza a Interface Visual (HTML)
 *******************************************************************************/
function rd_options_render() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Define a aba ativa (padrão é 'geral')
    $active_tab = isset($_GET['tab']) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'geral';

    // Pega o caminho absoluto da pasta do seu tema para buscar a imagem
    $theme_dir = get_template_directory_uri();

    // Lista mestre de abas para o loop
    $tabs = [
        'geral'       => 'Recursos Gerais',
        'privacidade' => 'Privacidade (LGPD)',
        'integracoes' => 'Integrações',
        'performance' => 'Performance',
        'redes'       => 'Redes Sociais',
        'seo'         => 'SEO',
        'interface'   => 'Doações',
        'ads'         => 'ADS',
        'manutencao'  => 'Manutenção'
    ];
    ?>
    <div class="wrap">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ccd0d4;">
            <h1 style="margin: 0; padding: 0;">Painel de Controle - ReloadeD</h1>
            <img src="<?php echo esc_url($theme_dir); ?>/assets/img/logo-reloaded-painel.webp" alt="ReloadeD Logo" style="max-height: 50px; width: auto;">
        </div>

            <?php settings_errors(); ?>

        <h2 class="nav-tab-wrapper">
            <?php foreach ($tabs as $id => $name) : ?>
                <a href="?page=rd_options&tab=<?php echo esc_attr( $id ); ?>"
                    class="nav-tab <?php echo $active_tab === $id ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html( $name ); ?>
                </a>
            <?php endforeach; ?>
        </h2>

        <form action="options.php" method="post" style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-top:none; margin-top:0;">
            <?php
            settings_fields('rd_options_group');

            foreach ($tabs as $id => $name) {
                $display = ($active_tab == $id) ? '' : 'display:none;';
                echo '<div class="rd-tab-content" id="tab-' . esc_attr( $id ) . '" style="' . esc_attr( $display ) . '">';
                do_settings_sections('rd_options_' . $id);
                echo '</div>';
            }

            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/*******************************************************************************
 * Registra os Campos e Seções no Banco de Dados
 *******************************************************************************/
function rd_settings_init() {
    register_setting('rd_options_group', 'rd_settings', 'rd_options_sanitize');

    // --- GERAL ---
    add_settings_section('sec_geral', 'Recursos do Tema', '__return_false', 'rd_options_geral');
    add_settings_field('back_to_top', 'Botão Voltar ao Topo', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'back_to_top', 'type' => 'checkbox', 'desc' => 'Ativa a exibição de um botão flutuante no canto inferior direito para o usuário retornar rapidamente ao topo da página.']);
    add_settings_field('enable_top_bar', 'Ativar Barra de Topo', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'enable_top_bar', 'type' => 'checkbox', 'desc' => 'Exibe uma pequena barra no topo com data, últimas notícias e redes sociais.']);
    add_settings_field('image_resizing', 'Redimensionar Imagens', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'image_resizing', 'type' => 'checkbox', 'desc' => 'Ativa a criação de recortes exatos (Hard Crop) das imagens enviadas para garantir que banners e cards fiquem sempre alinhados.']);
    add_settings_field('enable_thumb_control', 'Imagem Destacada', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'enable_thumb_control', 'type' => 'checkbox', 'desc' => 'Adiciona uma opção na barra lateral do editor de postagens para ocultar a imagem destacada na leitura do artigo (ideal para posts com vídeos no topo).']);
    add_settings_field('jpeg_quality', 'Qualidade das Imagens (%)', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'jpeg_quality', 'type' => 'number', 'default' => '90', 'desc' => 'Altera a qualidade das imagens O padrão do WP é 82. Valores menores deixam o site mais rápido, mas reduzem a qualidade visual.']);
    add_settings_field('comment_a11y', 'Acessibilidade dos Comentários', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'comment_a11y', 'type' => 'checkbox', 'desc' => 'Adiciona labels e atributos de preenchimento automático (autocomplete) ao formulário de comentários.']);
    add_settings_field('excerpt_text', 'Texto do botão "Leia Mais"', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'excerpt_text', 'type' => 'text', 'placeholder' => 'Ex: Continuar Lendo →', 'desc' => 'Personaliza o texto do botão de resumo. Deixe em branco para usar o padrão do tema.']);
    add_settings_field('comments_separator', 'Separador de Comentários', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'comments_separator', 'type' => 'text', 'desc' => 'Texto entre o Autor e o Post (ex: "comentou no post:").<br>Deixe <strong>vazio</strong> para o padrão do WP ou digite <strong>&amp;nbsp;</strong> para ocultar.']);
    add_settings_field('date_format', 'Formato da Data', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'date_format', 'type' => 'text', 'default' => 'l, j \d\e F \d\e Y', 'desc' => 'Ex: l, j \d\e F \d\e Y (Retorna: Segunda-feira, 29 de Abril de 2026). <a href="https://wordpress.org/documentation/article/customize-date-and-time-format/" target="_blank">Ver documentação do WP</a>.']);
    add_settings_field('enable_views_tracking', 'Contador de Visualizações', 'rd_master_field_cb', 'rd_options_geral', 'sec_geral', ['id' => 'enable_views_tracking', 'type' => 'checkbox', 'desc' => 'Ativa o sistema de contagem de visualizações por post. Um único IP conta apenas uma vez a cada 30 minutos. Bots conhecidos são ignorados automaticamente.']);

    // --- PRIVACIDADE ---
    add_settings_section('sec_priv', 'LGPD e Cookies', '__return_false', 'rd_options_privacidade');
    add_settings_field('enable_lgpd', 'LGPD - Banner de Cookies', 'rd_master_field_cb', 'rd_options_privacidade', 'sec_priv', ['id' => 'enable_lgpd', 'type' => 'checkbox', 'desc' => 'Ativa o banner de consentimento de cookies no rodapé do site para conformidade com a lei.']);
    add_settings_field('lgpd_text', 'Texto do Banner de Cookies', 'rd_master_field_cb', 'rd_options_privacidade', 'sec_priv', ['id' => 'lgpd_text', 'type' => 'textarea', 'desc' => 'Personalize a mensagem que aparece para o usuário. Você pode usar tags HTML como &lt;a&gt; para links.', 'rows' => 4]);

    // --- INTEGRAÇÕES ---
    add_settings_section('sec_int', 'Scripts e IDs', '__return_false', 'rd_options_integracoes');
    add_settings_field('ga_id', 'ID Google Analytics', 'rd_master_field_cb', 'rd_options_integracoes', 'sec_int', ['id' => 'ga_id', 'type' => 'text', 'placeholder' => 'G-XXXXXXX', 'desc' => 'Insira apenas o código de rastreamento (Tag ID). Deixe vazio para desativar.']);
    add_settings_field('discord_widget', 'Ativar Widget do Discord', 'rd_master_field_cb', 'rd_options_integracoes', 'sec_int', ['id' => 'discord_widget', 'type' => 'checkbox', 'desc' => 'Habilita a exibição do servidor do Discord na barra lateral.']);
    add_settings_field('discord_id', 'ID do Discord', 'rd_master_field_cb', 'rd_options_integracoes', 'sec_int', ['id' => 'discord_id', 'type' => 'text', 'desc' => 'ID do servidor (Server ID) para ativar a comunicação com o widget oficial na barra lateral.']);

    // --- PERFORMANCE ---
    add_settings_section('sec_perf', 'Otimização Técnica', '__return_false', 'rd_options_performance');
    add_settings_field('markdown_enabled', 'Markdown', 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'markdown_enabled', 'type' => 'checkbox', 'desc' => 'Ativa o suporte a sintaxe Markdown, permitindo escrever artigos (como no GitHub ou Docker Hub) nativamente.']);
    add_settings_field('prism_js', 'Destaque de Código', 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'prism_js', 'type' => 'checkbox', 'desc' => 'Ativa o suporte a Syntax Highlight, que colore códigos de programação. É carregado apenas em posts para performance.']);
    add_settings_field('disable_emojis', 'Desativar Emojis Nativos', 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'disable_emojis', 'type' => 'checkbox', 'desc' => 'Remove o script de emojis do WP. Navegadores modernos já renderizam emojis nativamente, ative isso para poupar requisições.']);
    add_settings_field('hide_wp_ver', 'Ocultar Versão do WP', 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'hide_wp_ver', 'type' => 'checkbox', 'desc' => 'Remove a meta tag geradora do WordPress do código-fonte. Uma boa prática de segurança básica.']);
    add_settings_field('facades_enabled', 'Sistema de Fachadas', 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'facades_enabled', 'type' => 'checkbox', 'desc' => 'Substitui iframes pesados (ex: YouTube) por uma imagem leve, carregando o player apenas quando o usuário clica.']);
    add_settings_field('disable_gutenberg_css', 'Desativa o CSS do WP (Bloat)', 'rd_master_field_cb', 'rd_options_performance', 'sec_perf', ['id' => 'disable_gutenberg_css', 'type' => 'checkbox', 'desc' => 'Remove o CSS global e de blocos do Gutenberg. Deixa o site mais leve, ideal para quem usa Markdown.']);

    // --- REDES SOCIAIS ---
    add_settings_section('sec_redes', 'Links das suas Redes Sociais', '__return_false', 'rd_options_redes');
    add_settings_field('social_discord', 'Discord', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_discord', 'type' => 'text', 'placeholder' => 'https://discord.gg/...', 'desc' => 'Link do seu servidor ou convite permanente para a comunidade.']);
    add_settings_field('social_telegram', 'Telegram', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_telegram', 'type' => 'text', 'placeholder' => 'https://t.me/...', 'desc' => 'Link do seu canal ou grupo oficial.']);
    add_settings_field('social_youtube', 'YouTube', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_youtube', 'type' => 'text', 'placeholder' => 'https://youtube.com/@...', 'desc' => 'URL do seu canal para exibição de vídeos e transmissões.']);
    add_settings_field('social_instagram', 'Instagram', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_instagram', 'type' => 'text', 'placeholder' => 'https://instagram.com/...', 'desc' => 'Link do seu perfil para fotos e atualizações visuais.']);
    add_settings_field('social_steam', 'Steam', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_steam', 'type' => 'text', 'placeholder' => 'https://steamcommunity.com/groups/...', 'desc' => 'Link do seu grupo na Steam ou perfil de curador.']);
    add_settings_field('social_twitter', 'Twitter (X)', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_twitter', 'type' => 'text', 'placeholder' => 'https://x.com/...', 'desc' => 'Link do seu perfil oficial no X.']);
    add_settings_field('social_facebook', 'Facebook', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_facebook', 'type' => 'text', 'placeholder' => 'https://facebook.com/...', 'desc' => 'Link da sua página oficial ou comunidade.']);
    add_settings_field('social_whatsapp', 'WhatsApp', 'rd_master_field_cb', 'rd_options_redes', 'sec_redes', ['id' => 'social_whatsapp', 'type' => 'text', 'placeholder' => 'https://wa.me/5511999999999', 'desc' => 'Link direto para o seu número ou grupo do WhatsApp (use o formato internacional).']);

    // --- SEO ---
    add_settings_section('sec_seo', 'SEO', '__return_false', 'rd_options_seo');
    add_settings_field('enable_open_graph', 'Meta Tags Open Graph', 'rd_master_field_cb', 'rd_options_seo', 'sec_seo', ['id' => 'enable_open_graph', 'type' => 'checkbox', 'desc' => 'Ativa o sistema de OG Tags. Gera as tags necessárias para redes sociais (Facebook, Discord, WhatsApp).']);
    add_settings_field('og_fallback_image', 'Imagem de Fallback (Open Graph)', 'rd_master_field_cb', 'rd_options_seo', 'sec_seo', ['id' => 'og_fallback_image', 'type' => 'media', 'desc' => 'Selecione uma imagem da sua biblioteca para ser o padrão nas redes sociais.']);

    // --- DOAÇÕES ---
    add_settings_section('sec_inter', 'Sistema de Doações', '__return_false', 'rd_options_interface');
    add_settings_field('github_sponsors', 'GitHub Sponsors', 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'github_sponsors', 'type' => 'text', 'placeholder' => 'https://github.com/sponsors/seu-usuario', 'desc' => 'Link para a sua página oficial de patrocínio no GitHub para apoiadores globais.']);
    add_settings_field('paypal_url', 'Link de Doação PayPal', 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'paypal_url', 'type' => 'text', 'placeholder' => 'https://www.paypal.com/donate?hosted_button_id=XXXX', 'desc' => 'URL direta da sua página de doação do PayPal.']);
    add_settings_field('paypal_qrcode', 'QR Code do PayPal', 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'paypal_qrcode', 'type' => 'media', 'desc' => 'Upload do QR Code do PayPal. Ao ser clicado no site, ele abrirá o link configurado acima.']);
    add_settings_field('pix_url', 'Link do PIX (Copia e Cola)', 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'pix_url', 'type' => 'text', 'placeholder' => 'https://nubank.com.br/pagar/xxx', 'desc' => 'URL direta para o pagamento PIX (se o seu banco fornecer link). O QR Code ficará clicável.']);
    add_settings_field('pix_qrcode', 'QR Code do PIX', 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'pix_qrcode', 'type' => 'media', 'desc' => 'Faça o upload da imagem do seu QR Code do PIX.']);
    add_settings_field('pix_chave', 'Chave PIX', 'rd_master_field_cb', 'rd_options_interface', 'sec_inter', ['id' => 'pix_chave', 'type' => 'text', 'placeholder' => 'email@dominio.com.br ou CPF/CNPJ', 'desc' => 'Sua chave PIX direta para apoiadores do Brasil.']);

    // --- ADS ---
    add_settings_section('sec_ads', 'Zonas de Publicidade', '__return_false', 'rd_options_ads');
    add_settings_field('ad_global', 'Script Global de Anúncios', 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_global', 'type' => 'textarea', 'desc' => 'Cole aqui a tag <head> global (ex: Auto Ads do AdSense).']);
    add_settings_field('ad_topo_desktop', 'Banner Topo - Desktop (728x90)', 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_topo_desktop', 'type' => 'textarea', 'desc' => 'Renderizado no cabeçalho apenas em telas grandes (PCs e Notebooks).']);
    add_settings_field('ad_topo_mobile', 'Banner Topo - Mobile (320x100)', 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_topo_mobile', 'type' => 'textarea', 'desc' => 'Renderizado no cabeçalho apenas em telas de smartphones.']);
    add_settings_field('ad_sidebar_top', 'Banner Sidebar - Topo (300x250)', 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_sidebar_top', 'type' => 'textarea', 'desc' => 'Renderizado na barra lateral, logo abaixo das integrações (ex: Discord).']);
    add_settings_field('ad_sidebar_sticky', 'Banner Sidebar - Sticky (300x600)', 'rd_master_field_cb', 'rd_options_ads', 'sec_ads', ['id' => 'ad_sidebar_sticky', 'type' => 'textarea', 'desc' => 'Renderizado no final da barra lateral. Acompanha a rolagem da tela.']);

    // --- MANUTENÇÃO ---
    add_settings_section('sec_manut', 'Controle de Acesso', '__return_false', 'rd_options_manutencao');
    add_settings_field('maintenance_mode', 'Ativar Manutenção', 'rd_master_field_cb', 'rd_options_manutencao', 'sec_manut', ['id' => 'maintenance_mode', 'type' => 'checkbox', 'desc' => 'Bloqueia o acesso de visitantes comuns e exibe uma tela de "Voltamos logo" (Retorna HTTP 503 para o Google).']);
    add_settings_field('maintenance_pass', 'Senha de Dev', 'rd_master_field_cb', 'rd_options_manutencao', 'sec_manut', ['id' => 'maintenance_pass', 'type' => 'password', 'desc' => 'Senha para desenvolvedores contornarem a manutenção. Acesse: <strong>seudominio.com.br/?rd-dev-login</strong> e digite a senha no formulário (não passe pela URL). A senha é armazenada como hash criptográfico — após salvar, este campo aparecerá vazio (isso é normal e seguro).']);
    add_settings_field('maintenance_text', 'Texto de Manutenção', 'rd_master_field_cb', 'rd_options_manutencao', 'sec_manut', ['id' => 'maintenance_text', 'type' => 'textarea', 'desc' => 'Personalize a mensagem que os visitantes verão na tela de bloqueio. Aceita HTML básico (ex: &lt;strong&gt;, &lt;br&gt;).']);
}
add_action('admin_init', 'rd_settings_init');

/*******************************************************************************
 * Funções de renderização dos campos (Reutilizáveis)
 *******************************************************************************/
function rd_master_field_cb($args) {
    // 1. Puxa as opções do banco de dados apenas uma vez
    $opt = get_option('rd_settings');

    // 2. Define o valor atual ou um fallback vazio
    $val = isset( $opt[$args['id']] ) ? $opt[$args['id']] : '';

    // 3. Define o tipo do campo (se não for passado, assume 'text' como padrão)
    $type = isset( $args['type'] ) ? $args['type'] : 'text';

    // 4. Monta o atributo 'name' dinamicamente
    $name = 'rd_settings[' . esc_attr( $args['id'] ) . ']';

    // 5. O Switch: Renderiza o HTML correto dependendo do 'type' solicitado
    switch ( $type ) {

        case 'media':
            echo '<div class="rd-media-container">';
            // O input oculto que realmente guarda a URL para o banco de dados
            echo '<input type="hidden" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '">';

            // A div de preview onde a miniatura da imagem vai aparecer
            echo '<div id="' . esc_attr( $args['id'] ) . '_preview" class="rd-media-preview" style="margin-bottom: 10px;">';
            if ( $val ) {
                echo '<img src="' . esc_url( $val ) . '" style="max-width: 200px; height: auto; border: 1px solid #ccc; display: block; border-radius: 4px;">';
            }
            echo '</div>';

            // Os botões de controle
            echo '<button type="button" class="button rd-upload-button" data-input-id="' . esc_attr( $args['id'] ) . '">Selecionar Imagem</button> ';

            $display = $val ? '' : 'display:none;';
            echo '<button type="button" class="button rd-remove-button" data-input-id="' . esc_attr( $args['id'] ) . '" style="' . $display . '">Remover</button>';
            echo '</div>';
        break;

        case 'checkbox':
            $val = isset( $opt[$args['id']] ) ? $opt[$args['id']] : 0;

            // Envolvemos tudo em uma tag <label> para que o texto fique na mesma linha e seja clicável
            echo '<label for="' . esc_attr( $args['id'] ) . '">';
            echo '<input type="checkbox" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="1" ' . checked( 1, $val, false ) . '>';

            // Se houver descrição, imprime ela aqui dentro da label, logo após o quadradinho
            if ( isset( $args['desc'] ) ) {
                echo ' ' . wp_kses_post( $args['desc'] );

                // O pulo do gato: removemos a descrição da variável para que
                // o código lá no final da função não imprima ela duplicada.
                unset( $args['desc'] );
            }
            echo '</label>';
        break;

        case 'textarea':
            echo '<textarea name="' . $name . '" rows="5" class="large-text">' . esc_textarea( $val ) . '</textarea>';
        break;

        case 'number':
            $min = isset( $args['min'] ) ? $args['min'] : 1;
            $max = isset( $args['max'] ) ? $args['max'] : 100;
            $val = empty($val) && isset($args['default']) ? $args['default'] : $val;

            // Renderiza o input com ID e a classe nativa do WP para manter o padrão
            echo '<input type="number" name="' . $name . '" id="' . esc_attr( $args['id'] ) . '" value="' . esc_attr( $val ) . '" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" class="small-text">';

            // Se houver descrição, imprime logo à frente do campo dentro de uma label
            if ( isset( $args['desc'] ) ) {
                echo ' <label for="' . esc_attr( $args['id'] ) . '">' . wp_kses_post( $args['desc'] ) . '</label>';

                // Remove a descrição da memória para não duplicar no final da função
                unset( $args['desc'] );
            }
        break;

        case 'select':
            echo '<select name="' . $name . '">';
            if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
                foreach ( $args['options'] as $key => $label ) {
                    echo '<option value="' . esc_attr( $key ) . '" ' . selected( $val, $key, false ) . '>' . esc_html( $label ) . '</option>';
                }
            }
            echo '</select>';
        break;

        case 'password':
            echo '<input type="password" name="' . $name . '" value="' . esc_attr( $val ) . '" class="regular-text">';
        break;

        case 'text':
        default:
            $placeholder = isset( $args['placeholder'] ) ? "placeholder='" . esc_attr( $args['placeholder'] ) . "'" : '';
            echo '<input type="text" name="' . $name . '" value="' . esc_attr( $val ) . '" class="regular-text" ' . $placeholder . '>';
        break;
    }

    // A descrição já é impressa perfeitamente aqui para todos os tipos!
    if ( isset( $args['desc'] ) ) {
        echo '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
    }
}

/*******************************************************************************
 * SANITIZAÇÃO DOS DADOS (Segurança)
 *******************************************************************************/
function rd_options_sanitize( $input ) {
    $new_input = array();
    foreach($input as $key => $value) {

        // 1. Zonas de Anúncios: Permite HTML e Scripts puros (AdSense, etc)
        if ( strpos($key, 'ad_') === 0 ) {
            $new_input[$key] = $value;
        }
        // 2. Campo LGPD: Permite HTML básico seguro (Links, negrito), mas bloqueia scripts
        elseif ( $key === 'lgpd_text' ) {
            $new_input[$key] = wp_kses_post($value);
        }
        // 3. Demais campos: Limpeza rigorosa, destrói qualquer HTML e script
        else {
            $new_input[$key] = sanitize_text_field($value);
        }
    }
    return $new_input;
}
