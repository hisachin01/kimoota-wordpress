<?php
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
});


function kimoota_get_start_year() {
    $host = $_SERVER['HTTP_HOST'] ?? '';

    switch ( $host ) {
        case 'mabinogi.kimoota.net':
            return 2007;

        case 'wordpress.kimoota.net':
        case 'kimoota.net':
        case 'www.kimoota.net':
            return 2015;

        default:
            return (int) date( 'Y' );
    }
}

add_filter( 'generate_copyright', function() {
    $start_year   = kimoota_get_start_year();
    $current_year = (int) date( 'Y' );
    $site_name    = get_bloginfo( 'name' );

    $year = ( $start_year < $current_year )
        ? $start_year . ' - ' . $current_year
        : $current_year;

    return sprintf(
        '© %s <strong>%s</strong>',
        esc_html( $year ),
        esc_html( $site_name )
    );
} );


/**
 * WP 6.9 & KUSANAGI 最適化：Highlight.js 統合処理
 */
add_action('wp_enqueue_scripts', function() {
    if (!is_single() || is_admin()) return;

    $version = '20260428_v1';
    $base_url = content_url('/plugins/kimoota-custom-assets/highlight/');

    // 1. CSS: preloadでレンダリングブロック回避
    wp_enqueue_style('hljs-style', $base_url . 'styles/tomorrow-night-bright.min.css', array(), $version);

    // 2. JS: WP 6.9標準のdefer戦略（第4引数はnullでstrategyを優先）
    wp_enqueue_script('hljs-script', $base_url . 'highlight.custom.min.js', array(), $version, array(
        'strategy'  => 'defer',
        'in_footer' => true,
    ));
}, 20);

/**
 * CSS preload フィルター（noscript補完付き）
 */
add_filter('style_loader_tag', function($tag, $handle) {
    if ($handle !== 'hljs-style') return $tag;
    return str_replace(
        "rel='stylesheet'",
        "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\" media='all'",
        $tag
    ) . '<noscript>' . $tag . '</noscript>';
}, 10, 2);

/**
 * フッター：警告抑止・自動判定・コピーボタン一括処理
 */
add_action('wp_footer', function() {
    if (!is_single()) return;
?>
<script>
(() => {
    const runHighlight = () => {
        if (typeof hljs === 'undefined') return;

        hljs.configure({ ignoreUnescapedHTML: true });

        document.querySelectorAll('pre').forEach((pre) => {
            if (pre.dataset.highlighted) return;
            
            const code = pre.querySelector('code') || pre;
            const text = pre.innerText;
            if (!code.classList.contains('hljs') && (text.includes('$ ') || text.includes('wp '))) {
                code.classList.add('language-bash');
            }

            hljs.highlightElement(code);
            pre.dataset.highlighted = 'true';

            // 【構造変更】pre の外側に、ラッパー（外枠 div）を自動生成して包む
            if (!pre.parentElement.classList.contains('hljs-container')) {
                const wrapper = document.createElement('div');
                wrapper.className = 'hljs-container';
                pre.parentNode.insertBefore(wrapper, pre);
                wrapper.appendChild(pre);

                // コピーボタンは pre ではなく、外枠の wrapper に付ける
                const btn = document.createElement('button');
                btn.className = 'copy-code-button';
                btn.type = 'button';
                btn.setAttribute('aria-label', 'Copy code');
                btn.innerHTML = '<span>Copy</span>';
                wrapper.appendChild(btn);

                btn.onclick = () => {
                    let cleanText = code.innerText.replace(/Copy|OK!/g, '').trim();
                    cleanText = cleanText.split('\n').map(l => l.replace(/^[#$]\s?/, '')).join('\n');

                    navigator.clipboard.writeText(cleanText).then(() => {
                        const span = btn.querySelector('span');
                        span.innerText = 'OK!';
                        setTimeout(() => { span.innerText = 'Copy'; }, 2000);
                    });
                };
            }
        });
    };

    if (document.readyState === 'loading') {
        window.addEventListener('load', runHighlight);
    } else {
        runHighlight();
    }
})();
</script>

<style>
/* 1. すべての基準となる外枠（檻） */
.hljs-container {
    position: relative !important;
    width: 100% !important;
    max-width: 100% !important;
    margin: 1.5em 0 !important;
    box-sizing: border-box !important;
}

/* 2. 親要素 pre：外枠の中で絶対にはみ出さない */
pre {
    background: #1d1f21 !important;
    padding: 50px 1.5em 1.5em !important; /* 上部にボタン用の余白 */
    border-radius: 8px !important;
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
    font-size: 14px !important;
    line-height: 1.6 !important;
    color: #eae7e4;
    
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow: hidden !important; /* 物理的なはみ出しを完全封鎖 */
    margin: 0 !important;
}

/* 3. 中身の code：通常の折り返し指定のみでスマートに管理 */
pre code {
    display: block !important;
    width: 100% !important;
    max-width: 100% !important;
    background: transparent !important;
    box-sizing: border-box !important;
    overflow-x: visible !important;

    /* 標準的な折り返しセット（全角カッコになればこれで100%折れます） */
    white-space: pre-wrap !important;       
    word-wrap: break-word !important;       
    word-break: break-all !important;       
    
    /* 折り返し行のインデント（見やすさ向上） */
    padding-left: 2em !important;
    text-indent: -2em !important;
}

/* 4. コピーボタン：定位置固定 */
.copy-code-button {
    position: absolute !important;
    top: 12px !important;
    right: 12px !important;
    z-index: 30 !important;
    background: rgba(233, 150, 122, 0.15) !important;
    border: 1px solid rgba(233, 150, 122, 0.4) !important;
    color: #E9967A !important;
    padding: 4px 10px !important;
    font-size: 11px !important;
    font-weight: bold;
    border-radius: 4px !important;
    cursor: pointer !important;
    outline: none !important;
    transition: all 0.2s;
}

.copy-code-button:hover {
    background: rgba(233, 150, 122, 0.3) !important;
    border-color: #E9967A !important;
}

/* スマホ表示の最適化 */
@media screen and (max-width: 480px) {
    pre {
        padding-top: 60px !important;
        font-size: 13px !important;
    }
    .copy-code-button {
        padding: 6px 12px !important;
    }
}
</style>
<?php
}, 9999);

// アイキャッチ調整

add_filter('post_thumbnail_html', function ($html) {
    if (is_single() && !is_admin() && strpos($html, 'wp-post-image') !== false) {
        $html = preg_replace(
            '/sizes="[^"]*"/',
            'sizes="(max-width: 768px) 100vw, 800px"',
            $html
        );
    }
    return $html;
}, 9999);



//-------------------------------------------------------
//WordPressのサイトマップリダイレクト
//-------------------------------------------------------

add_filter( 'wp_sitemaps_enabled', '__return_false' );

/* 短縮URL削除 */
remove_action('wp_head', 'wp_shortlink_wp_head');

/**
 * 管理画面で設定したサイズ(800, 400, 200)以外に追加するサイズ
 */
function my_extra_image_sizes() {
    // ブログカード等、Retina表示で「ちょうどいい」中間サイズ
    // 280pxの2倍 = 560px。これがあるとPSIで「デカすぎる」と怒られにくい。
    add_image_size('size_560_blogcar-ratina', 560, 0, false);
add_image_size('size_280_blogcard', 280, 0, false);
    // 最小の正方形（アイコンやアバター用）
    add_image_size('size_128_square', 128, 0, false);
        // 最小の正方形（アイコンやアバター用）
    add_image_size('size_80_square', 80, 80, true);
    add_image_size('size_720_top-ratina', 720, 0, false);
    add_image_size('size_640_top-ratina', 720, 0, false);
}
add_action('after_setup_theme', 'my_extra_image_sizes');

/**
 * medium_large (768px) の生成を完全に停止する
 */
add_filter( 'intermediate_image_sizes_advanced', function( $sizes ) {
    unset( $sizes['medium_large'] );
    return $sizes;
});

/*このは管理画面*/
// フックする関数
function custom_enqueue($hook_suffix) {
  if( 'post.php' == $hook_suffix ||
      'post-new.php' == $hook_suffix ||
      'edit.php' == $hook_suffix ||
      'index.php' == $hook_suffix) {
    // 読み込むCSSファイル
    wp_enqueue_style('custom_css', get_stylesheet_directory_uri() . '/custom.css');
  }
}
// "custom_enqueue" 関数を管理画面のキューアクションにフック
add_action( 'admin_enqueue_scripts', 'custom_enqueue' );

//Alt属性がないIMGタグにalt=""を追加する
function non_alt_fix($content){
  $content = preg_replace('/<img((?![^>]*alt=)[^>]*)>/i', '<img alt=""${1}>', $content);
  return $content;
}
add_filter('the_content', 'non_alt_fix');

/**
 * 1. 投稿者アーカイブURLとパラメータ (?author=N) を404にする
 * これがあれば rewrite_rules をいじる必要はありません
 */
add_action( 'template_redirect', function() {
    if ( is_author() || isset( $_GET['author'] ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }
});

/**
 * 2. クラス名からユーザーID（author-1 など）を削除して隠蔽する
 */
add_filter( 'body_class', function( $classes ) {
    return array_filter($classes, function($class) {
        return strpos($class, 'author-') === false;
    });
});

add_filter( 'comment_class', function( $classes ) {
    return array_filter($classes, function($class) {
        return strpos($class, 'comment-author-') === false;
    });
});

/**
 * 3. REST API からユーザー情報を隠す
 */
add_filter( 'rest_endpoints', function( $endpoints ) {
    if ( isset( $endpoints['/wp/v2/users'] ) ) unset( $endpoints['/wp/v2/users'] );
    if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
    return $endpoints;
});

/**
 * 4. コメント非表示
 */
add_filter('comments_open','__return_false');

/**
 * 5. Feedにアイキャッチ画像を表示する
 */
function rss_post_thumbnail($content) {
    global $post;
    if(has_post_thumbnail($post->ID)) {
        $content = '<p>' . get_the_post_thumbnail($post->ID, 'medium') . '</p>' . $content;
    }
    return $content;
}
add_filter('the_excerpt_rss', 'rss_post_thumbnail');
add_filter('the_content_feed', 'rss_post_thumbnail');

/**
 * 記事ページの前後ナビを「同一カテゴリー内」に限定する
 */
add_filter( 'generate_post_navigation_args', function( $args ) {
    $args['in_same_term'] = true;       // 同一カテゴリー内に限定
    $args['taxonomy']     = 'category'; // カテゴリーで判定
    return $args;
} );

/**
 * 記事ページ（シングル）以外の全ページで、
 * 「前へ・次へ」のテキストリンクのみを非表示にする
 */
add_action('wp_head', function() {
    // 記事ページ（is_single）ではない場合のみ適用
    if ( ! is_single() ) {
        echo '<style id="kimoota-nav-cleaner">
            /* GPのページネーションからテキストリンク（prev/next）を抹殺 */
            .paging-navigation .nav-previous,
            .paging-navigation .nav-next,
            .paging-navigation .prev,
            .paging-navigation .next {
                display: none !important;
            }
            
            /* 数字（①②③）だけは表示を維持 */
            .paging-navigation .page-numbers:not(.prev):not(.next) {
                display: inline-block !important;
            }

            /* 余計な隙間を詰めるための微調整 */
            .paging-navigation .nav-links {
                display: flex;
                justify-content: center;
                gap: 10px;
            }
        </style>';
    }
}, 100);

/**
 * ページタイプに応じたクリティカルCSSのインライン出力（FOUC・CLS対策）
 */
add_action('wp_head', function() {
    
    // 1. トップページ（フロントページ・ホーム）の場合
    if ( is_front_page() || is_home() ) {
        echo '<style id="kimoota-critical-css-top">' . "\n";
        ?>
        /* --- [トップ用] 変数・基本設定 --- */
        :root { 
            --accent-color: #fa8072; 
            --base-bg: #1a1a1a; 
            --text-color: #eeeeee; 
        }
        html { 
            background-color: var(--base-bg); 
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }
        html, body { 
            overflow-x: hidden !important; 
            width: 100% !important; 
            position: relative; 
            margin: 0; 
            color: var(--text-color); 
        }
        body { 
            font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; 
            font-size: 17px; 
            line-height: 1.7; /* ★スタイルCSS（1.7）に合わせてガクつきを防止 */
            letter-spacing: 0.02em; /* ★スタイルCSSの設定をここにも先取り */
        }

        /* --- [トップ用] レイアウト・MV・パーツ設定 --- */
        .grid-container { margin: 0 auto; max-width: 1200px; }
        .site-header { position: relative; background: var(--base-bg); }
        .inside-header { display: flex; align-items: center; padding: 20px 40px; }
        .main-navigation .inside-navigation { display: flex; align-items: center; justify-content: space-between; }
        
        /* タイトルの文字詰めルール */
        .appeal-title { font-feature-settings: "palt"; }
        
        .entry-content { line-height: 1.8; margin-top: 2em; }
        .entry-content h2 { margin-top: 2.5em; margin-bottom: 1em; color: var(--accent-color); }

        /* --- [トップ用] レスポンシブ --- */
        @media (max-width: 1080px) {
            .inside-header { flex-direction: column; text-align: center; }
            .site-content { flex-direction: column; }
            .one-container .site-content { padding: 40px 15px; } 
            .entry-content { padding: 0 20px; }
            /* メインコンテンツとサイドバーの親要素が暴れないようにする */
#content {
    display: flex;
    flex-wrap: wrap;
    clear: both;
}

/* モバイル等で崩れないよう、幅を再定義 */
@media (max-width:768px){
    #primary,
    .content-area{
        width:100% !important;
    }
}
    #primary {
        width: 70%; /* テーマの数値に合わせて微調整してください */
    }
    
    
          /* --- モバイル（959px以下）でタイトルを左寄せにする --- */
@media (max-width: 1080px) {
    .site-header .inside-header {
        /* コンテナ内の並びを「左端（タイトル）と右端（ボタン群）」に強制分割 */
        justify-content: flex-start !important; 
    }

    /* タイトルロゴが中央に引っ張られるのを防ぎ、左端に固定する */
    .site-branding,
    .site-logo,
    .navigation-branding {
        margin-right: auto !important;
        margin-left: 0 !important;
        text-align: left !important;
        padding-left: 15px; /* 左端に適度な余白を作る */
    }
}  
        
        <?php
        echo '</style>' . "\n";
    }
    
    // 2. 投稿ページ（記事詳細シングルページ）の場合
    elseif ( is_singular() ) {
        echo '<style id="kimoota-critical-css-single">' . "\n";
        ?>
        /* --- [投稿ページ用] 骨格設定 --- */
        :root { 
            --accent-color: #fa8072; 
            --base-bg: #1a1a1a; 
            --text-color: #eeeeee; 
        }
        html { 
            background-color: var(--base-bg); 
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }
        html, body { 
            overflow-x: hidden !important; 
            width: 100% !important; 
            position: relative; 
            margin: 0; 
            color: var(--text-color); 
        }
        body { 
            font-family: -apple-system, system-ui, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; 
            font-size: 17px; 
            line-height: 1.7; /* ★スタイルCSS（1.7）に合わせてガクつきを防止 */
            letter-spacing: 0.02em; /* ★スタイルCSSの設定をここにも先取り */
        }

        /* コンテナの中央寄せ構造 */
        .grid-container { 
            margin: 0 auto !important; 
            max-width: 1200px !important; 
            width: 100% !important;
        }
        
        /* ヘッダー・コンテンツ周りの初期配置 */
        .site-header { position: relative; background: var(--base-bg); }
        .inside-header { display: flex; align-items: center; padding: 20px 40px; }
        .entry-content { line-height: 1.8; margin-top: 2em; }
        .entry-content h2 { margin-top: 2.5em; margin-bottom: 1em; color: var(--accent-color); }

        /* --- [投稿ページ用] レスポンシブ --- */
        @media (max-width: 768px) {
            .inside-header { flex-direction: column; text-align: center; }
            .site-content { flex-direction: column; }
            .one-container .site-content { padding: 40px 15px !important; } 
            .entry-content { padding: 0 !important; }
        }
                /* --- モバイル（959px以下）でタイトルを左寄せにする --- */
@media (max-width: 1080px) {
    .site-header .inside-header {
        /* コンテナ内の並びを「左端（タイトル）と右端（ボタン群）」に強制分割 */
        justify-content: flex-start !important; 
    }

    /* タイトルロゴが中央に引っ張られるのを防ぎ、左端に固定する */
    .site-branding,
    .site-logo,
    .navigation-branding {
        margin-right: auto !important;
        margin-left: 0 !important;
        text-align: left !important;
        padding-left: 15px; /* 左端に適度な余白を作る */
    }
}
        
        /* --- GeneratePress layout critical: single page --- */
.single .site-content {
    display: flex;
    flex-wrap: wrap;

    padding: 40px;
    box-sizing: border-box;
     clear: both;
}

.single .content-area,
.single .site-main,
.single .inside-article,
.single .widget-area,
.single .sidebar {
    box-sizing: border-box;
}

.single .inside-article {
    padding: 0 0 30px 0;
}

/* site-main の初期marginを最終表示に合わせる */
.single.separate-containers.right-sidebar .site-main {
    margin: 20px 20px 20px 0 !important;
}

@media (max-width: 1080px) {
    .single.separate-containers.right-sidebar .site-main {
        margin: 0 !important;
    }
}

.single.right-sidebar #primary {
    width: 70%;
}

.single.right-sidebar #right-sidebar {
    width: 30%;
}

.single.no-sidebar #primary {
    width: 100%;
    max-width: 840px;
    margin-left: auto;
    margin-right: auto;
}

@media (max-width: 1080px) {
    .single .site-content {
        display: block;
        padding: 40px 15px;
    }

    .single.right-sidebar #primary,
    .single.right-sidebar #right-sidebar,
    .single.no-sidebar #primary {
        width: 100%;
        max-width: 100%;
        margin-left: 0;
        margin-right: 0;
    }

    .single.right-sidebar .site-main{
        margin-left: 0;
        margin-right: 0;
    }
}

@media (max-width: 768px) {
    .separate-containers .inside-article,
    .separate-containers .comments-area,
    .separate-containers .page-header,
    .separate-containers .paging-navigation,
    .inside-page-header {
        padding: 15px !important;
    }
}
/* ----------------------------------------------------------------             
  固定ページ
----------------------------------------------------------------- */

.page .site-content {
    display: flex;
    flex-wrap: wrap;

    padding: 40px;
    box-sizing: border-box;
    clear: both;
}

.page .content-area,
.page .site-main,
.page .inside-article,
.page .widget-area,
.page .sidebar {
    box-sizing: border-box;
}

.page .inside-article {
    padding: 0 0 30px 0;
}

/* site-main の初期marginを最終表示に合わせる */
.page.separate-containers.right-sidebar .site-main {
    margin: 20px 20px 20px 0 !important;
}

@media (max-width: 1080px) {
    .page.separate-containers.right-sidebar .site-main {
        margin: 0 !important;
    }
}

.page.right-sidebar #primary {
    width: 70%;
}

.page.right-sidebar #right-sidebar {
    width: 30%;
}

.page.no-sidebar #primary {
    width: 100%;
    max-width: 840px;
    margin-left: auto;
    margin-right: auto;
}

@media (max-width: 1080px) {
    .page .site-content {
        display: block;
        padding: 40px 15px;
    }

    .page.right-sidebar #primary,
    .page.right-sidebar #right-sidebar,
    .page.no-sidebar #primary {
        width: 100%;
        max-width: 100%;
        margin-left: 0;
        margin-right: 0;
    }

    .page.right-sidebar .site-main {
        margin-left: 0;
        margin-right: 0;
    }
}

@media (max-width: 768px) {
    .separate-containers .inside-article,
    .separate-containers .comments-area,
    .separate-containers .page-header,
    .separate-containers .paging-navigation,
    .inside-page-header {
        padding: 15px !important;
    }
}

/* ----------------------------------------------------------------             
  パンくずリスト
----------------------------------------------------------------- */

.breadcrumb {
  max-width: 1200px;
  margin: 20px auto 10px;
  padding: 0 30px;
  font-size: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
  color: #b8b8b8;
}

.breadcrumb .sep::before {
  content: ">";
  margin: 0 6px;
  color: #9a9a9a;
}

.breadcrumb .bc-home,
.breadcrumb .bc-cat {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.breadcrumb a {
  color: #b8b8b8;
  text-decoration: none;
}

.breadcrumb a:hover {
  color: #ffffff;
}

.breadcrumb .current {
  color: #d0d0d0;
}

@media (max-width: 768px) {
  .breadcrumb {
    padding: 0 15px;
    font-size: 11px;
  }
}

/* GP標準のアイキャッチ周辺余白 */
.single .post-image:not(:first-child) {
    margin-top: 2em;
}

.single .inside-article > [class*="page-header-"] {
    margin-top: 0;
    margin-bottom: 2em;
}

.single .inside-article .page-header-image-single.page-header-below-title {
    margin-top: 2em;
}

/* 画像の初期表示安定化 */
.single .post-image,
.single .featured-image,
.single .page-header-image-single {
    line-height: 0;
    overflow: hidden;
}

.single .post-image img,
.single .featured-image img,
.single .page-header-image-single img {
    vertical-align: bottom;
    box-sizing: border-box;
}

/* 記事本文側の初期幅暴れ防止 */
.single .entry-content,
.single .entry-header,
.single .custom-entry-meta-container {
    max-width: 840px;
    margin-left: auto;
    margin-right: auto;
    box-sizing: border-box;
}



.single .featured-image {
    width: 100%;
    max-width: 840px;
    margin: 0 auto 25px !important;
}

.single .featured-image img {
    display: block;
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 9;
}

@media (max-width: 1080px) {
    .single #primary.content-area {
        width: 100%;
        max-width: 100%;
    }

    .single .featured-image {
        width: 100%;
        max-width: 100%;
    }
}


        /* --- 1. タイトルエリア全体 --- */
.entry-header {
    margin-bottom: 20px;
}

/* 擬似要素（装飾）のリセット */
.entry-title::after,
.entry-header::after {
    display: none !important;
    content: none !important;
}

.entry-title {
    font-size: 28px;
    line-height: 1.3;
    font-weight: 700;
    color: #eee;
    margin-top: 15px !important;
    margin-bottom: 20px !important;
    display: block !important; 
}

/* --- 3. カスタムメタ情報コンテナ --- */
.custom-entry-meta-container {
    margin-top: 20px !important;
}

.meta-row.taxonomy-links {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

/* カテゴリバッジ */
.meta-cat-badge {
    background: #333;
    color: #eee !important;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    border: 1px solid #444;
    text-decoration: none !important;
    transition: background 0.2s;
}
.meta-cat-badge:hover {
    background: #444;
}

/* タグ：バッジ風にアップグレード */
.meta-tag-item {
    font-size: 11px; /* 少し小さくして密度を上げる */
    color: #bbb !important;
    background: #2a2a2a; /* 控えめなバッジ背景 */
    padding: 2px 10px;
    border-radius: 2px;
    border: 1px solid #333;
    text-decoration: none !important;
    transition: all 0.2s ease;
}

.meta-tag-item:hover {
    background: #E9967A; /* 差し色のサーモンピンク */
    color: #1a1a1a !important;
    border-color: #E9967A;
    transform: translateY(-1px);
}

/* 日付エリア：著者情報を削り、さらに静謐に */
.meta-row.date-author {
    font-size: 12px;
    color: #9f9f9f !important; /* 日付は背景に馴染ませる */
    margin-top: 8px;
}

/* --- 4. 差し色の下線（アクセント） --- */
.single .custom-entry-meta-container::after {
    content: '';
    display: block;
    width: 60px;
    height: 2px;
    background: #E9967A;
    margin-top: 25px;
}

/* サイドバーウィジェットの初期表示安定化 */

.single .sidebar .widget,
.single .footer-widgets .widget {
    font-size: 17px;
}

.single .widget-title {
    margin-bottom: 30px;
    font-size: 20px;
    line-height: 1.5;
    font-weight: normal;
}

.single .widget ul,
.single .widget ol {
    margin: 0;
}

.single .widget ul li {
    list-style-type: none;
    position: relative;
    padding-bottom: 5px;
}

.single .widget .search-field {
    width: 100%;
    box-sizing: border-box;
}

.single .widget_search .search-submit {
    display: none;
}

/* サイドバーCLS対策：最小限 */
.single .inside-right-sidebar {
    width: 100%;
    max-width: 100%;
    margin-top: 20px;
    margin-bottom: 20px;
    box-sizing: border-box;
}

.single .widget-area .widget {
    width: 100%;
    padding: 16px !important;
    margin: 0 0 30px;
    box-sizing: border-box;
    background: #1a1a1a;
    color: #eee;
}

.single .widget-area img {
    display: block;
    max-width: 100%;
    height: auto;
}


/* --- 記事下SNSボタン：CLS対策用 critical --- */
.single .site-main {
    display: flex;
    flex-direction: column;
}

.single .entry-content {
    order: 1;
}

.single .article-share-buttons {
    order: 2;
    display: flex;
    justify-content: space-between;
    gap: 8px;
    width: 100%;
    margin: 40px 0 20px;
}

.single footer.entry-meta {
    order: 3;
    display: block !important;
}

.article-share-buttons .share-btn {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    min-height: 58px;
    padding: 12px 0;
    box-sizing: border-box;

    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 4px;
    color: #ccc !important;
    text-decoration: none !important;
}

.article-share-buttons .share-btn svg {
    width: 20px;
    height: 20px;
    display: block;
    flex: 0 0 20px;
    fill: currentColor;
    margin-bottom: 5px;
}

.article-share-buttons .share-btn .btn-text-icon {
    line-height: 1;
    margin-bottom: 4px;
}

.article-share-buttons .share-btn .large-b {
    font-size: 22px !important;
    font-weight: 900;
    line-height: 1;
}

.article-share-buttons .share-btn span:not(.btn-text-icon) {
    font-size: 10px;
    font-weight: bold;
    line-height: 1.2;
    text-transform: uppercase;
}

@media screen and (max-width: 600px) {
    .single .article-share-buttons {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 40px;
    }
}

/* --- 5. 不要なメタ情報を消去（最終防衛ライン） --- */

/* 標準メタの非表示 */
.entry-header .entry-meta {
    display: none !important;
}

/* 記事下（footer）の重複メタを排除 */
footer.entry-meta .meta-row,
footer.entry-meta .cat-links,
footer.entry-meta .tags-links,
footer.entry-meta .posted-on {
    display: none !important;
}

/* ページナビゲーションの表示確保 */
#nav-below.post-navigation {
    display: block !important;
    visibility: visible !important;
}

/* ページナビ内のアイコン非表示 */
.post-navigation .gp-icon {
    display: none !important;
}

        <?php
        echo '</style>' . "\n";
    }

}, 1);



