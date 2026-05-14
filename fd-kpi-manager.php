<?php
/**
 * Plugin Name: FD方針・KPI管理プラグイン
 * Plugin URI:  https://example.com/
 * Description: 保険代理店向け「お客様本位の業務運営（FD）方針」およびKPIを管理・表示するプラグイン
 * Version:     1.0.0
 * Author:      Your Agency
 * Text Domain: fd-kpi-manager
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FD_KPI_VERSION',    '1.0.0' );
define( 'FD_KPI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FD_KPI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ============================================================
   1. KPIマスタ カスタム投稿タイプの登録
   ============================================================ */
add_action( 'init', 'fd_kpi_register_post_types' );
function fd_kpi_register_post_types() {

    // KPIマスタ
    register_post_type( 'kpi_master', [
        'labels' => [
            'name'               => 'KPIマスタ',
            'singular_name'      => 'KPIマスタ',
            'add_new'            => '新規追加',
            'add_new_item'       => '新しいKPI評価項目を追加',
            'edit_item'          => 'KPI評価項目を編集',
            'new_item'           => '新しいKPI評価項目',
            'view_item'          => 'KPI評価項目を表示',
            'search_items'       => 'KPI評価項目を検索',
            'not_found'          => 'KPI評価項目が見つかりません',
            'not_found_in_trash' => 'ゴミ箱にKPI評価項目はありません',
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => true,
        'menu_label'        => 'KPIマスタ',
        'menu_icon'         => 'dashicons-chart-bar',
        'supports'          => [ 'title' ],
        'capability_type'   => 'post',
        'has_archive'       => false,
        'rewrite'           => false,
    ] );
}

/* ============================================================
   2. メタボックスの登録
   ============================================================ */
add_action( 'add_meta_boxes', 'fd_kpi_add_meta_boxes' );
function fd_kpi_add_meta_boxes() {
    add_meta_box(
        'fd_kpi_metabox',
        'FD方針・KPI 入力フォーム',
        'fd_kpi_metabox_callback',
        'post',
        'normal',
        'high'
    );
}

function fd_kpi_metabox_callback( $post ) {
    wp_nonce_field( 'fd_kpi_save_meta', 'fd_kpi_nonce' );

    // 保存済みデータを取得
    $data     = get_post_meta( $post->ID, '_fd_kpi_data', true );
    $intro    = isset( $data['intro'] )    ? $data['intro']    : '';
    $link_url = isset( $data['link_url'] ) ? $data['link_url'] : '';
    $groups   = isset( $data['groups'] )   ? $data['groups']   : [];

    // KPIマスタ一覧を取得
    $kpi_masters = get_posts( [
        'post_type'      => 'kpi_master',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ] );
    ?>
    <div id="fd-kpi-wrap">

        <!-- 基本項目 -->
        <table class="form-table fd-kpi-base-table">
            <tr>
                <th><label for="fd_intro">冒頭文</label></th>
                <td>
                    <textarea id="fd_intro" name="fd_kpi[intro]" rows="5"
                        style="width:100%;"><?php echo esc_textarea( $intro ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th><label for="fd_link_url">リンクURL（金融庁原則）</label></th>
                <td>
                    <input type="url" id="fd_link_url" name="fd_kpi[link_url]"
                        value="<?php echo esc_url( $link_url ); ?>"
                        style="width:100%;" placeholder="https://"/>
                </td>
            </tr>
        </table>

        <hr/>
        <h3>方針・KPIグループ</h3>
        <p class="description">「グループを追加」ボタンで方針を追加できます。各方針内に「取り組み」と「KPI」を複数登録可能です。</p>

        <!-- 繰り返しグループコンテナ -->
        <div id="fd-kpi-groups-container">
            <?php
            if ( ! empty( $groups ) ) {
                foreach ( $groups as $g_idx => $group ) {
                    fd_kpi_render_group( $g_idx, $group, $kpi_masters );
                }
            }
            ?>
        </div>

        <button type="button" id="fd-kpi-add-group" class="button button-primary" style="margin-top:12px;">
            ＋ グループ（方針）を追加
        </button>

        <!-- テンプレート（非表示） -->
        <script type="text/html" id="fd-kpi-group-tpl">
            <?php fd_kpi_render_group( '__GI__', [], $kpi_masters, true ); ?>
        </script>
        <script type="text/html" id="fd-kpi-approach-tpl">
            <?php fd_kpi_render_approach( '__GI__', '__AI__', [], true ); ?>
        </script>
        <script type="text/html" id="fd-kpi-kpi-tpl">
            <?php fd_kpi_render_kpi_row( '__GI__', '__KI__', [], $kpi_masters, true ); ?>
        </script>
    </div>
    <?php
}

/* ---- レンダリングヘルパー ---- */

function fd_kpi_render_group( $g_idx, $group, $kpi_masters, $is_tpl = false ) {
    $heading   = isset( $group['heading'] )   ? $group['heading']   : '';
    $principle = isset( $group['principle'] ) ? $group['principle'] : '';
    $approaches = isset( $group['approaches'] ) ? $group['approaches'] : [];
    $kpis       = isset( $group['kpis'] )       ? $group['kpis']       : [];

    $principles = [ '原則2', '原則3', '原則4', '原則5', '原則6', '原則7' ];
    ?>
    <div class="fd-kpi-group" data-group="<?php echo esc_attr( $g_idx ); ?>"
         style="border:1px solid #ccd0d4;border-radius:4px;padding:16px;margin-bottom:16px;background:#fff;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <strong style="font-size:14px;">方針グループ <span class="fd-group-num"></span></strong>
            <button type="button" class="button fd-kpi-remove-group" style="color:#b32d2e;">✕ このグループを削除</button>
        </div>

        <table class="form-table" style="margin:0;">
            <tr>
                <th style="width:140px;"><label>見出し（h2）</label></th>
                <td>
                    <input type="text" name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][heading]"
                           value="<?php echo esc_attr( $heading ); ?>" style="width:100%;"
                           placeholder="例：お客様の最善の利益を優先した提案"/>
                </td>
            </tr>
            <tr>
                <th><label>原則</label></th>
                <td>
                    <select name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][principle]" style="width:200px;">
                        <?php foreach ( $principles as $p ) : ?>
                            <option value="<?php echo esc_attr( $p ); ?>"
                                <?php selected( $principle, $p ); ?>>
                                <?php echo esc_html( $p ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <!-- 具体的な取り組み -->
        <div style="margin-top:14px;">
            <strong>具体的な取り組み</strong>
            <div class="fd-approaches-container" style="margin-top:8px;">
                <?php
                if ( ! empty( $approaches ) ) {
                    foreach ( $approaches as $a_idx => $approach ) {
                        fd_kpi_render_approach( $g_idx, $a_idx, $approach );
                    }
                }
                ?>
            </div>
            <button type="button" class="button fd-kpi-add-approach" data-group="<?php echo esc_attr( $g_idx ); ?>"
                    style="margin-top:6px;">＋ 取り組みを追加</button>
        </div>

        <!-- KPI -->
        <div style="margin-top:14px;">
            <strong>KPI</strong>
            <div class="fd-kpis-container" style="margin-top:8px;">
                <?php
                if ( ! empty( $kpis ) ) {
                    foreach ( $kpis as $k_idx => $kpi ) {
                        fd_kpi_render_kpi_row( $g_idx, $k_idx, $kpi, $kpi_masters );
                    }
                }
                ?>
            </div>
            <button type="button" class="button fd-kpi-add-kpi" data-group="<?php echo esc_attr( $g_idx ); ?>"
                    style="margin-top:6px;">＋ KPIを追加</button>
        </div>
    </div>
    <?php
}

function fd_kpi_render_approach( $g_idx, $a_idx, $approach, $is_tpl = false ) {
    $val = is_array( $approach ) ? ( isset( $approach['text'] ) ? $approach['text'] : '' ) : $approach;
    ?>
    <div class="fd-approach-row" style="display:flex;gap:8px;margin-bottom:6px;align-items:center;">
        <input type="text"
               name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][approaches][<?php echo esc_attr( $a_idx ); ?>][text]"
               value="<?php echo esc_attr( $val ); ?>"
               style="flex:1;" placeholder="取り組み内容を入力"/>
        <button type="button" class="button fd-kpi-remove-approach" style="color:#b32d2e;">✕</button>
    </div>
    <?php
}

function fd_kpi_render_kpi_row( $g_idx, $k_idx, $kpi, $kpi_masters, $is_tpl = false ) {
    $selected_id    = isset( $kpi['master_id'] )           ? $kpi['master_id']           : '';
    $last_goal      = isset( $kpi['last_year_goal'] )      ? $kpi['last_year_goal']      : '';
    $last_actual    = isset( $kpi['last_year_actual'] )    ? $kpi['last_year_actual']    : '';
    $this_goal      = isset( $kpi['this_year_goal'] )      ? $kpi['this_year_goal']      : '';
    ?>
    <div class="fd-kpi-row" style="border:1px dashed #ccd0d4;border-radius:4px;padding:10px;margin-bottom:8px;">
        <div style="display:flex;justify-content:flex-end;margin-bottom:6px;">
            <button type="button" class="button fd-kpi-remove-kpi" style="color:#b32d2e;">✕ KPIを削除</button>
        </div>
        <table class="form-table" style="margin:0;">
            <tr>
                <th style="width:130px;"><label>KPI評価項目</label></th>
                <td>
                    <select name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][master_id]"
                            style="width:260px;">
                        <option value="">-- KPIを選択 --</option>
                        <?php foreach ( $kpi_masters as $km ) : ?>
                            <option value="<?php echo esc_attr( $km->ID ); ?>"
                                <?php selected( $selected_id, $km->ID ); ?>>
                                <?php echo esc_html( $km->post_title ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label>昨年度目標</label></th>
                <td><input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][last_year_goal]"
                           value="<?php echo esc_attr( $last_goal ); ?>" style="width:200px;"/></td>
            </tr>
            <tr>
                <th><label>昨年度実績</label></th>
                <td><input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][last_year_actual]"
                           value="<?php echo esc_attr( $last_actual ); ?>" style="width:200px;"/></td>
            </tr>
            <tr>
                <th><label>本年度目標</label></th>
                <td><input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][this_year_goal]"
                           value="<?php echo esc_attr( $this_goal ); ?>" style="width:200px;"/></td>
            </tr>
        </table>
    </div>
    <?php
}

/* ============================================================
   3. メタデータの保存
   ============================================================ */
add_action( 'save_post', 'fd_kpi_save_meta' );
function fd_kpi_save_meta( $post_id ) {

    // オートセーブ・権限・Nonce チェック
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    if ( ! isset( $_POST['fd_kpi_nonce'] ) ||
         ! wp_verify_nonce( $_POST['fd_kpi_nonce'], 'fd_kpi_save_meta' ) ) return;
    if ( ! isset( $_POST['fd_kpi'] ) ) return;

    $raw = $_POST['fd_kpi'];

    // サニタイズ
    $sanitized = [];
    $sanitized['intro']    = sanitize_textarea_field( $raw['intro'] ?? '' );
    $sanitized['link_url'] = esc_url_raw( $raw['link_url'] ?? '' );

    $sanitized['groups'] = [];
    if ( ! empty( $raw['groups'] ) && is_array( $raw['groups'] ) ) {
        foreach ( $raw['groups'] as $g ) {
            $group = [];
            $group['heading']   = sanitize_text_field( $g['heading']   ?? '' );
            $group['principle'] = sanitize_text_field( $g['principle'] ?? '' );

            $group['approaches'] = [];
            if ( ! empty( $g['approaches'] ) && is_array( $g['approaches'] ) ) {
                foreach ( $g['approaches'] as $a ) {
                    $text = sanitize_text_field( $a['text'] ?? '' );
                    if ( $text !== '' ) {
                        $group['approaches'][] = [ 'text' => $text ];
                    }
                }
            }

            $group['kpis'] = [];
            if ( ! empty( $g['kpis'] ) && is_array( $g['kpis'] ) ) {
                foreach ( $g['kpis'] as $k ) {
                    $group['kpis'][] = [
                        'master_id'       => absint( $k['master_id']       ?? 0 ),
                        'last_year_goal'  => sanitize_text_field( $k['last_year_goal']  ?? '' ),
                        'last_year_actual'=> sanitize_text_field( $k['last_year_actual'] ?? '' ),
                        'this_year_goal'  => sanitize_text_field( $k['this_year_goal']  ?? '' ),
                    ];
                }
            }

            $sanitized['groups'][] = $group;
        }
    }

    update_post_meta( $post_id, '_fd_kpi_data', $sanitized );
}

/* ============================================================
   4. スクリプト・スタイルの読み込み
   ============================================================ */
add_action( 'admin_enqueue_scripts', 'fd_kpi_admin_scripts' );
function fd_kpi_admin_scripts( $hook ) {
    global $post_type;
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ] ) ) return;
    if ( $post_type !== 'post' ) return;

    wp_enqueue_style(
        'fd-kpi-admin',
        FD_KPI_PLUGIN_URL . 'assets/admin.css',
        [],
        FD_KPI_VERSION
    );
    wp_enqueue_script(
        'fd-kpi-admin',
        FD_KPI_PLUGIN_URL . 'assets/admin.js',
        [ 'jquery' ],
        FD_KPI_VERSION,
        true
    );
}

/* ============================================================
   5. フロントエンド出力（the_content フック）
   ============================================================ */
add_filter( 'the_content', 'fd_kpi_the_content_filter' );
function fd_kpi_the_content_filter( $content ) {
    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
        return $content;
    }
    $post_id = get_the_ID();
    $data    = get_post_meta( $post_id, '_fd_kpi_data', true );
    if ( empty( $data ) ) return $content;

    ob_start();
    fd_kpi_render_frontend( $data );
    $fd_html = ob_get_clean();

    return $fd_html . $content;
}

/* ---- ショートコード版（任意のページでも使用可能） ---- */
add_shortcode( 'fd_kpi', 'fd_kpi_shortcode' );
function fd_kpi_shortcode( $atts ) {
    $a = shortcode_atts( [ 'id' => get_the_ID() ], $atts );
    $data = get_post_meta( absint( $a['id'] ), '_fd_kpi_data', true );
    if ( empty( $data ) ) return '';
    ob_start();
    fd_kpi_render_frontend( $data );
    return ob_get_clean();
}

/* ---- フロントエンド HTML 生成 ---- */
function fd_kpi_render_frontend( $data ) {
    $intro    = isset( $data['intro'] )    ? $data['intro']    : '';
    $link_url = isset( $data['link_url'] ) ? $data['link_url'] : '#';
    $groups   = isset( $data['groups'] )   ? $data['groups']   : [];

    // 設定からクラスを取得
    $h2_class = fd_kpi_get_style_option( 'h2_class' );
    $h3_class = fd_kpi_get_style_option( 'h3_class' );

    // 冒頭文
    if ( $intro ) {
        echo '<p class="fd-kpi-intro">' . nl2br( esc_html( $intro ) ) . '</p>';
    }

    // 固定文
    ?>
    <p class="fd-kpi-notice">
        本方針は、金融庁が公表する「顧客本位の業務運営に関する原則（<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">原則全文</a>）」および消費者庁等で構成する消費者志向経営推進組織が実施する「消費者志向自主宣言」に対応したものです。
    </p>
    <?php

    // 方針セクション（ループ）
    $all_kpis = []; // 最下部テーブル用に全KPIを収集

    foreach ( $groups as $g_num => $group ) {
        $heading   = isset( $group['heading'] )   ? $group['heading']   : '';
        $principle = isset( $group['principle'] ) ? $group['principle'] : '';
        $approaches = isset( $group['approaches'] ) ? $group['approaches'] : [];
        $kpis       = isset( $group['kpis'] )       ? $group['kpis']       : [];

        $display_num = $g_num + 1;

        // h2: 方針{num} {見出し}（{原則}）
        echo '<h2 class="' . esc_attr( $h2_class ) . ' fd-kpi-policy-heading">';
        echo '方針' . esc_html( $display_num ) . ' ' . esc_html( $heading );
        if ( $principle ) {
            echo '（' . esc_html( $principle ) . '）';
        }
        echo '</h2>';

        // 具体的な取り組み
        if ( ! empty( $approaches ) ) {
            echo '<h3 class="' . esc_attr( $h3_class ) . ' fd-kpi-approach-heading">具体的な取り組み</h3>';
            echo '<ul class="fd-kpi-approach-list">';
            foreach ( $approaches as $a ) {
                $text = is_array( $a ) ? ( $a['text'] ?? '' ) : $a;
                if ( $text ) {
                    echo '<li>' . esc_html( $text ) . '</li>';
                }
            }
            echo '</ul>';
        }

        // KPI セクション
        if ( ! empty( $kpis ) ) {
            echo '<h3 class="' . esc_attr( $h3_class ) . ' fd-kpi-kpi-heading">KPI</h3>';
            // figure.wp-block-table でラップ → Lightning の wp-block-table スタイルがそのまま適用される
            echo '<div class="fd-kpi-table-wrap"><figure class="wp-block-table is-style-vk-table-border">';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>評価項目</th><th>昨年度目標</th><th>昨年度実績</th><th>本年度目標</th>';
            echo '</tr></thead><tbody>';

            foreach ( $kpis as $kpi ) {
                $master_id  = isset( $kpi['master_id'] ) ? absint( $kpi['master_id'] ) : 0;
                $kpi_title  = $master_id ? get_the_title( $master_id ) : '';
                $last_goal  = isset( $kpi['last_year_goal'] )   ? $kpi['last_year_goal']   : '';
                $last_actual= isset( $kpi['last_year_actual'] ) ? $kpi['last_year_actual'] : '';
                $this_goal  = isset( $kpi['this_year_goal'] )   ? $kpi['this_year_goal']   : '';

                echo '<tr>';
                echo '<td>' . esc_html( $kpi_title )   . '</td>';
                echo '<td>' . esc_html( $last_goal )   . '</td>';
                echo '<td>' . esc_html( $last_actual ) . '</td>';
                echo '<td>' . esc_html( $this_goal )   . '</td>';
                echo '</tr>';

                // 集約用
                $all_kpis[] = [
                    'title'       => $kpi_title,
                    'last_goal'   => $last_goal,
                    'last_actual' => $last_actual,
                    'this_goal'   => $this_goal,
                ];
            }
            echo '</tbody></table></figure></div>';
        }
    }

    // KPIの公表（最下部）
    if ( ! empty( $all_kpis ) ) {
        ?>
        <h2 class="<?php echo esc_attr( $h2_class ); ?> fd-kpi-disclosure-heading">KPIの公表</h2>
        <p class="fd-kpi-disclosure-lead">
            「お客様本位の業務運営方針」の定着度合いを客観的に評価できるようにするための成果指標（KPI）の取組結果を公表いたします。
        </p>
        <div class="fd-kpi-table-wrap">
        <figure class="wp-block-table is-style-vk-table-border">
            <table>
                <thead>
                    <tr>
                        <th>評価項目</th>
                        <th>昨年度目標</th>
                        <th>昨年度実績</th>
                        <th>本年度目標</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $all_kpis as $kpi ) : ?>
                    <tr>
                        <td><?php echo esc_html( $kpi['title'] );       ?></td>
                        <td><?php echo esc_html( $kpi['last_goal'] );   ?></td>
                        <td><?php echo esc_html( $kpi['last_actual'] ); ?></td>
                        <td><?php echo esc_html( $kpi['this_goal'] );   ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </figure>
        </div>
        <?php
    }
}

/* ============================================================
   6. フロントエンド CSS の読み込み
   ============================================================ */
add_action( 'wp_enqueue_scripts', 'fd_kpi_frontend_styles' );
function fd_kpi_frontend_styles() {
    wp_enqueue_style(
        'fd-kpi-frontend',
        FD_KPI_PLUGIN_URL . 'assets/frontend.css',
        [],
        FD_KPI_VERSION
    );
}

/* ============================================================
   7. スタイル設定ヘルパー
      get_option で保存値を取得。未設定時はデフォルト値を返す。
   ============================================================ */
function fd_kpi_get_style_option( $key ) {
    $defaults = [
        'h2_class' => 'is-style-vk-heading-primary',
        'h3_class' => 'is-style-vk-heading-secondary',
        'h4_class' => 'is-style-vk-heading-plain',
    ];
    $saved = get_option( 'fd_kpi_styles', [] );
    if ( isset( $saved[ $key ] ) && $saved[ $key ] !== '' ) {
        return sanitize_html_class( $saved[ $key ] );
    }
    return $defaults[ $key ] ?? '';
}

/* ============================================================
   8. 管理画面：スタイル設定ページ
   ============================================================ */
add_action( 'admin_menu', 'fd_kpi_add_settings_page' );
function fd_kpi_add_settings_page() {
    add_submenu_page(
        'edit.php?post_type=kpi_master',   // KPIマスタメニューの下に追加
        'FD-KPI スタイル設定',
        'スタイル設定',
        'manage_options',
        'fd-kpi-styles',
        'fd_kpi_render_settings_page'
    );
}

function fd_kpi_render_settings_page() {
    // 保存処理
    if ( isset( $_POST['fd_kpi_styles_nonce'] ) &&
         wp_verify_nonce( $_POST['fd_kpi_styles_nonce'], 'fd_kpi_save_styles' ) &&
         current_user_can( 'manage_options' ) ) {

        $save = [
            'h2_class' => sanitize_html_class( $_POST['fd_kpi_h2_class'] ?? '' ),
            'h3_class' => sanitize_html_class( $_POST['fd_kpi_h3_class'] ?? '' ),
            'h4_class' => sanitize_html_class( $_POST['fd_kpi_h4_class'] ?? '' ),
        ];
        update_option( 'fd_kpi_styles', $save );
        echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
    }

    $saved = get_option( 'fd_kpi_styles', [] );
    $h2 = $saved['h2_class'] ?? '';
    $h3 = $saved['h3_class'] ?? '';
    $h4 = $saved['h4_class'] ?? '';
    ?>
    <div class="wrap">
        <h1>FD-KPI スタイル設定</h1>
        <p>フロントエンドの各見出しに付与する <code>is-style-*</code> クラスを設定します。<br>
           ブロックエディタで見出しに付けているクラス名（<code>is-style-</code> を含む全体）をそのまま入力してください。</p>

        <form method="post">
            <?php wp_nonce_field( 'fd_kpi_save_styles', 'fd_kpi_styles_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="fd_kpi_h2_class">
                            H2 クラス<br>
                            <small style="font-weight:normal;color:#666;">方針見出し・「KPIの公表」</small>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="fd_kpi_h2_class" name="fd_kpi_h2_class"
                               value="<?php echo esc_attr( $h2 ); ?>"
                               placeholder="is-style-vk-heading-primary"
                               class="regular-text" />
                        <p class="description">未入力時のデフォルト: <code>is-style-vk-heading-primary</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fd_kpi_h3_class">
                            H3 クラス<br>
                            <small style="font-weight:normal;color:#666;">「具体的な取り組み」「KPI」</small>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="fd_kpi_h3_class" name="fd_kpi_h3_class"
                               value="<?php echo esc_attr( $h3 ); ?>"
                               placeholder="is-style-vk-heading-secondary"
                               class="regular-text" />
                        <p class="description">未入力時のデフォルト: <code>is-style-vk-heading-secondary</code></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="fd_kpi_h4_class">
                            H4 クラス<br>
                            <small style="font-weight:normal;color:#666;">（将来拡張用・現在未使用）</small>
                        </label>
                    </th>
                    <td>
                        <input type="text" id="fd_kpi_h4_class" name="fd_kpi_h4_class"
                               value="<?php echo esc_attr( $h4 ); ?>"
                               placeholder="is-style-vk-heading-plain"
                               class="regular-text" />
                        <p class="description">未入力時のデフォルト: <code>is-style-vk-heading-plain</code></p>
                    </td>
                </tr>
            </table>

            <hr>
            <h2 style="font-size:14px;margin-bottom:4px;">入力例（クラス名をそのままコピペしてください）</h2>
            <ul style="list-style:disc;padding-left:1.5em;color:#444;font-size:13px;line-height:2;">
                <li><code>is-style-vk-heading-primary</code></li>
                <li><code>is-style-vk-heading-secondary</code></li>
                <li><code>is-style-vk-heading-plain</code></li>
                <li><code>is-style-vk-heading-double_black</code>（追加CSSクラスで定義したカスタムスタイル）</li>
                <li><code>is-style-vk-heading-background_fill_lightgray</code>（追加CSSクラスで定義したカスタムスタイル）</li>
            </ul>

            <?php submit_button( '設定を保存' ); ?>
        </form>
    </div>
    <?php
}
