<?php
/**
 * Plugin Name: FD方針・KPI管理プラグイン
 * Plugin URI:  https://example.com/
 * Description: 保険代理店向け「お客様本位の業務運営（FD）方針」およびKPIを管理・表示するプラグイン
 * Version:     1.1.0
 * Author:      Your Agency
 * Text Domain: fd-kpi-manager
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'FD_KPI_VERSION',    '1.1.0' );
define( 'FD_KPI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FD_KPI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ============================================================
   1. KPIマスタ カスタム投稿タイプ
   ============================================================ */
add_action( 'init', 'fd_kpi_register_post_types' );
function fd_kpi_register_post_types() {
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
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'fd-kpi-manager',   // 専用メニューの配下に表示
        'supports'        => [ 'title' ],
        'capability_type' => 'post',
        'has_archive'     => false,
        'rewrite'         => false,
    ] );
}

/* ============================================================
   2. 管理メニュー登録
   ============================================================ */
add_action( 'admin_menu', 'fd_kpi_admin_menu' );
function fd_kpi_admin_menu() {
    // トップレベルメニュー（= 一覧ページ）
    add_menu_page(
        'FD方針・KPI管理',
        'FD方針・KPI',
        'manage_options',
        'fd-kpi-manager',
        'fd_kpi_admin_page',
        'dashicons-clipboard',
        30
    );
    // 一覧ページへの明示的なサブメニュー
    add_submenu_page(
        'fd-kpi-manager',
        'FD方針・KPI 一覧',
        'FD方針・KPI 一覧',
        'manage_options',
        'fd-kpi-manager',
        'fd_kpi_admin_page'
    );
    // スタイル設定
    add_submenu_page(
        'fd-kpi-manager',
        'FD-KPI スタイル設定',
        'スタイル設定',
        'manage_options',
        'fd-kpi-styles',
        'fd_kpi_render_settings_page'
    );
    // ※ KPIマスタは kpi_master CPT の show_in_menu でこのメニュー下に自動追加される
}

/* ============================================================
   3. 保存・削除を admin_init で先行処理（リダイレクト前）
   ============================================================ */
add_action( 'admin_init', 'fd_kpi_handle_admin_actions' );
function fd_kpi_handle_admin_actions() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'fd-kpi-manager' ) {
        return;
    }

    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';

    /* ---- 削除 ---- */
    if ( $action === 'delete' ) {
        $record_id = isset( $_GET['record_id'] ) ? sanitize_text_field( $_GET['record_id'] ) : '';
        if ( ! $record_id ) {
            return;
        }
        if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'fd_kpi_delete_' . $record_id ) ) {
            wp_die( '不正なリクエストです。' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( '権限がありません。' );
        }

        $records = fd_kpi_get_records();
        $records = array_values(
            array_filter( $records, fn( $r ) => $r['id'] !== $record_id )
        );
        fd_kpi_save_records( $records );

        wp_safe_redirect( admin_url( 'admin.php?page=fd-kpi-manager&deleted=1' ) );
        exit;
    }

    /* ---- 保存 ---- */
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        isset( $_POST['fd_kpi_nonce'] ) &&
        wp_verify_nonce( $_POST['fd_kpi_nonce'], 'fd_kpi_save_record' ) &&
        current_user_can( 'manage_options' )
    ) {
        $records   = fd_kpi_get_records();
        $record_id = sanitize_text_field( $_POST['fd_kpi_record_id'] ?? '' );
        $raw       = $_POST['fd_kpi'] ?? [];

        $sanitized             = fd_kpi_sanitize_record( $raw );
        $sanitized['id']       = $record_id ?: uniqid( 'fdkpi_' );
        $sanitized['title']    = sanitize_text_field( $_POST['fd_kpi_title'] ?? '' );
        $sanitized['status']   = ( ( $_POST['fd_kpi_status'] ?? '' ) === 'publish' ) ? 'publish' : 'draft';

        // 既存レコード更新 or 末尾に追加
        $found = false;
        foreach ( $records as &$r ) {
            if ( $r['id'] === $sanitized['id'] ) {
                $r     = $sanitized;
                $found = true;
                break;
            }
        }
        unset( $r );
        if ( ! $found ) {
            $records[] = $sanitized;
        }

        fd_kpi_save_records( $records );

        wp_safe_redirect(
            admin_url(
                'admin.php?page=fd-kpi-manager&action=edit'
                . '&record_id=' . urlencode( $sanitized['id'] )
                . '&saved=1'
            )
        );
        exit;
    }
}

/* ============================================================
   4. データ取得 / 保存ヘルパー
   ============================================================ */
function fd_kpi_get_records() {
    return get_option( 'fd_kpi_records', [] );
}

function fd_kpi_save_records( array $records ) {
    update_option( 'fd_kpi_records', $records );
}

function fd_kpi_get_record( string $id ) {
    foreach ( fd_kpi_get_records() as $r ) {
        if ( $r['id'] === $id ) {
            return $r;
        }
    }
    return null;
}

/* ============================================================
   5. 管理ページ振り分け
   ============================================================ */
function fd_kpi_admin_page() {
    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';

    if ( $action === 'edit' ) {
        fd_kpi_render_edit_page();
    } else {
        fd_kpi_render_list_page();
    }
}

/* ============================================================
   6. 一覧ページ
   ============================================================ */
function fd_kpi_render_list_page() {
    $records = fd_kpi_get_records();
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">FD方針・KPI 一覧</h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=fd-kpi-manager&action=edit' ) ); ?>"
           class="page-title-action">新規追加</a>
        <hr class="wp-header-end">

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>保存しました。</p></div>
        <?php endif; ?>
        <?php if ( isset( $_GET['deleted'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>削除しました。</p></div>
        <?php endif; ?>

        <?php if ( empty( $records ) ) : ?>
            <p style="margin-top:20px;">レコードがありません。「新規追加」から登録してください。</p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th scope="col" style="width:40px;">No.</th>
                        <th scope="col">管理タイトル</th>
                        <th scope="col" style="width:80px;">ステータス</th>
                        <th scope="col" style="width:180px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $records as $i => $record ) :
                        $rid        = $record['id'];
                        $rec_status = $record['status'] ?? 'publish';
                        $edit_url   = admin_url(
                            'admin.php?page=fd-kpi-manager&action=edit&record_id=' . urlencode( $rid )
                        );
                        $delete_url = wp_nonce_url(
                            admin_url(
                                'admin.php?page=fd-kpi-manager&action=delete&record_id=' . urlencode( $rid )
                            ),
                            'fd_kpi_delete_' . $rid
                        );
                        ?>
                        <tr>
                            <td><?php echo esc_html( $i + 1 ); ?></td>
                            <td>
                                <strong>
                                    <a href="<?php echo esc_url( $edit_url ); ?>">
                                        <?php echo esc_html( $record['title'] ?: '（タイトルなし）' ); ?>
                                    </a>
                                </strong>
                            </td>
                            <td>
                                <?php if ( $rec_status === 'publish' ) : ?>
                                    <span style="color:#2e7d32;font-weight:600;">公開</span>
                                <?php else : ?>
                                    <span style="color:#888;">非公開</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( $edit_url ); ?>">編集</a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url( $delete_url ); ?>"
                                   style="color:#b32d2e;"
                                   onclick="return confirm('このレコードを削除しますか？');">削除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p style="margin-top:20px;color:#555;font-size:13px;">
            ショートコード <code>[fd_kpi]</code> を固定ページに貼ると、ステータスが「公開」のレコードをすべて出力します。
        </p>
    </div>
    <?php
}

/* ============================================================
   7. 編集ページ（新規 / 既存）
   ============================================================ */
function fd_kpi_render_edit_page() {
    $record_id = isset( $_GET['record_id'] ) ? sanitize_text_field( $_GET['record_id'] ) : '';
    $record    = $record_id ? fd_kpi_get_record( $record_id ) : null;
    $is_new    = ( $record === null );

    $title    = $record['title']    ?? '';
    $status   = $record['status']   ?? 'publish';
    $intro    = $record['intro']    ?? '';
    $link_url = $record['link_url'] ?? '';
    $groups   = $record['groups']   ?? [];

    $kpi_masters = get_posts( [
        'post_type'      => 'kpi_master',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ] );

    $list_url = admin_url( 'admin.php?page=fd-kpi-manager' );
    ?>
    <div class="wrap">
        <h1><?php echo $is_new ? '新規レコード追加' : 'レコード編集'; ?></h1>
        <a href="<?php echo esc_url( $list_url ); ?>">← 一覧に戻る</a>

        <?php if ( isset( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible" style="margin-top:12px;">
                <p>保存しました。</p>
            </div>
        <?php endif; ?>

        <form method="post" style="margin-top:20px;">
            <?php wp_nonce_field( 'fd_kpi_save_record', 'fd_kpi_nonce' ); ?>
            <input type="hidden" name="fd_kpi_record_id"
                   value="<?php echo esc_attr( $record_id ); ?>"/>

            <!-- 管理用フィールド -->
            <table class="form-table fd-kpi-base-table">
                <tr>
                    <th style="width:160px;"><label for="fd_kpi_title">管理タイトル</label></th>
                    <td>
                        <input type="text" id="fd_kpi_title" name="fd_kpi_title"
                               value="<?php echo esc_attr( $title ); ?>"
                               style="width:420px;"
                               placeholder="例：2024年度 FD方針"/>
                        <p class="description">管理画面の一覧表示用です。フロントには出力されません。</p>
                    </td>
                </tr>
                <tr>
                    <th><label>ステータス</label></th>
                    <td>
                        <label>
                            <input type="radio" name="fd_kpi_status" value="publish"
                                <?php checked( $status, 'publish' ); ?>>
                            公開
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            <input type="radio" name="fd_kpi_status" value="draft"
                                <?php checked( $status, 'draft' ); ?>>
                            非公開
                        </label>
                        <p class="description">
                            「公開」のレコードのみ <code>[fd_kpi]</code> ショートコードで出力されます。
                        </p>
                    </td>
                </tr>
            </table>

            <hr/>

            <!-- FD方針・KPI 入力フォーム -->
            <div id="fd-kpi-wrap">
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
                <p class="description">
                    「グループを追加」ボタンで方針を追加できます。
                    各方針内に「取り組み」と「KPI」を複数登録可能です。
                </p>

                <div id="fd-kpi-groups-container">
                    <?php
                    if ( ! empty( $groups ) ) {
                        foreach ( $groups as $g_idx => $group ) {
                            fd_kpi_render_group( $g_idx, $group, $kpi_masters );
                        }
                    }
                    ?>
                </div>

                <button type="button" id="fd-kpi-add-group"
                        class="button button-primary" style="margin-top:12px;">
                    ＋ グループ（方針）を追加
                </button>

                <!-- JS テンプレート（非表示） -->
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

            <p style="margin-top:20px;">
                <?php submit_button( '保存する', 'primary', 'submit', false ); ?>
                &nbsp;
                <a href="<?php echo esc_url( $list_url ); ?>" class="button">キャンセル</a>
            </p>
        </form>
    </div>
    <?php
}

/* ============================================================
   8. 入力値サニタイズ
   ============================================================ */
function fd_kpi_sanitize_record( array $raw ): array {
    $sanitized             = [];
    $sanitized['intro']    = sanitize_textarea_field( $raw['intro']    ?? '' );
    $sanitized['link_url'] = esc_url_raw(             $raw['link_url'] ?? '' );
    $sanitized['groups']   = [];

    if ( ! empty( $raw['groups'] ) && is_array( $raw['groups'] ) ) {
        foreach ( $raw['groups'] as $g ) {
            $group              = [];
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
                        'master_id'        => absint( $k['master_id']        ?? 0 ),
                        'last_year_goal'   => sanitize_text_field( $k['last_year_goal']   ?? '' ),
                        'last_year_actual' => sanitize_text_field( $k['last_year_actual'] ?? '' ),
                        'this_year_goal'   => sanitize_text_field( $k['this_year_goal']   ?? '' ),
                    ];
                }
            }

            $sanitized['groups'][] = $group;
        }
    }

    return $sanitized;
}

/* ============================================================
   9. 管理画面スクリプト / スタイル読み込み
      ※ 専用ページの編集画面のみ読み込む
   ============================================================ */
add_action( 'admin_enqueue_scripts', 'fd_kpi_admin_scripts' );
function fd_kpi_admin_scripts( $hook ) {
    // toplevel_page_{slug} がトップレベルメニューのフック名
    if ( $hook !== 'toplevel_page_fd-kpi-manager' ) {
        return;
    }
    $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
    if ( $action !== 'edit' ) {
        return;
    }

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
   10. ショートコード [fd_kpi]
       公開ステータスのレコードをすべて順番に出力する
   ============================================================ */
add_shortcode( 'fd_kpi', 'fd_kpi_shortcode' );
function fd_kpi_shortcode( $atts ) {
    $records = fd_kpi_get_records();
    if ( empty( $records ) ) {
        return '';
    }

    ob_start();
    foreach ( $records as $record ) {
        if ( ( $record['status'] ?? 'publish' ) !== 'publish' ) {
            continue;
        }
        fd_kpi_render_frontend( $record );
    }
    return ob_get_clean();
}

/* ============================================================
   11. フロントエンド HTML 生成
   ============================================================ */
function fd_kpi_render_frontend( array $data ) {
    $intro    = $data['intro']    ?? '';
    $link_url = $data['link_url'] ?? '#';
    $groups   = $data['groups']   ?? [];

    $h2_class = fd_kpi_get_style_option( 'h2_class' );
    $h3_class = fd_kpi_get_style_option( 'h3_class' );

    // 冒頭文
    if ( $intro ) {
        echo '<p class="fd-kpi-intro">' . nl2br( esc_html( $intro ) ) . '</p>';
    }

    // 固定案内文
    ?>
    <p class="fd-kpi-notice">
        本方針は、金融庁が公表する「顧客本位の業務運営に関する原則（<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">原則全文</a>）」および消費者庁等で構成する消費者志向経営推進組織が実施する「消費者志向自主宣言」に対応したものです。
    </p>
    <?php

    $all_kpis = [];

    foreach ( $groups as $g_num => $group ) {
        $heading    = $group['heading']    ?? '';
        $principle  = $group['principle']  ?? '';
        $approaches = $group['approaches'] ?? [];
        $kpis       = $group['kpis']       ?? [];
        $display_num = $g_num + 1;

        // H2 見出し
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

        // KPI テーブル
        if ( ! empty( $kpis ) ) {
            echo '<h3 class="' . esc_attr( $h3_class ) . ' fd-kpi-kpi-heading">KPI</h3>';
            echo '<div class="fd-kpi-table-wrap"><figure class="wp-block-table is-style-vk-table-border">';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>評価項目</th><th>昨年度目標</th><th>昨年度実績</th><th>本年度目標</th>';
            echo '</tr></thead><tbody>';

            foreach ( $kpis as $kpi ) {
                $master_id   = isset( $kpi['master_id'] ) ? absint( $kpi['master_id'] ) : 0;
                $kpi_title   = $master_id ? get_the_title( $master_id ) : '';
                $last_goal   = $kpi['last_year_goal']   ?? '';
                $last_actual = $kpi['last_year_actual'] ?? '';
                $this_goal   = $kpi['this_year_goal']   ?? '';

                echo '<tr>';
                echo '<td>' . esc_html( $kpi_title )   . '</td>';
                echo '<td>' . esc_html( $last_goal )   . '</td>';
                echo '<td>' . esc_html( $last_actual ) . '</td>';
                echo '<td>' . esc_html( $this_goal )   . '</td>';
                echo '</tr>';

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

    // KPIの公表（集約テーブル）
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
   12. フロントエンド CSS 読み込み
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
   13. スタイル設定ヘルパー
   ============================================================ */
function fd_kpi_get_style_option( string $key ): string {
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
   14. スタイル設定ページ（変更なし）
   ============================================================ */
function fd_kpi_render_settings_page() {
    if (
        isset( $_POST['fd_kpi_styles_nonce'] ) &&
        wp_verify_nonce( $_POST['fd_kpi_styles_nonce'], 'fd_kpi_save_styles' ) &&
        current_user_can( 'manage_options' )
    ) {
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
                               class="regular-text"/>
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
                               class="regular-text"/>
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
                               class="regular-text"/>
                        <p class="description">未入力時のデフォルト: <code>is-style-vk-heading-plain</code></p>
                    </td>
                </tr>
            </table>

            <hr>
            <h2 style="font-size:14px;margin-bottom:4px;">入力例</h2>
            <ul style="list-style:disc;padding-left:1.5em;color:#444;font-size:13px;line-height:2;">
                <li><code>is-style-vk-heading-primary</code></li>
                <li><code>is-style-vk-heading-secondary</code></li>
                <li><code>is-style-vk-heading-plain</code></li>
                <li><code>is-style-vk-heading-double_black</code></li>
                <li><code>is-style-vk-heading-background_fill_lightgray</code></li>
            </ul>

            <?php submit_button( '設定を保存' ); ?>
        </form>
    </div>
    <?php
}

/* ============================================================
   15. 管理画面レンダリングヘルパー
       （メタボックス時代から変更なし。編集ページから呼び出す）
   ============================================================ */
function fd_kpi_render_group( $g_idx, $group, $kpi_masters, $is_tpl = false ) {
    $heading    = $group['heading']    ?? '';
    $principle  = $group['principle']  ?? '';
    $approaches = $group['approaches'] ?? [];
    $kpis       = $group['kpis']       ?? [];
    $principles = [ '原則2', '原則3', '原則4', '原則5', '原則6', '原則7' ];
    ?>
    <div class="fd-kpi-group"
         data-group="<?php echo esc_attr( $g_idx ); ?>"
         style="border:1px solid #ccd0d4;border-radius:4px;padding:16px;margin-bottom:16px;background:#fff;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
            <strong style="font-size:14px;">方針グループ <span class="fd-group-num"></span></strong>
            <button type="button" class="button fd-kpi-remove-group" style="color:#b32d2e;">
                ✕ このグループを削除
            </button>
        </div>

        <table class="form-table" style="margin:0;">
            <tr>
                <th style="width:140px;"><label>見出し（h2）</label></th>
                <td>
                    <input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][heading]"
                           value="<?php echo esc_attr( $heading ); ?>"
                           style="width:100%;"
                           placeholder="例：お客様の最善の利益を優先した提案"/>
                </td>
            </tr>
            <tr>
                <th><label>原則</label></th>
                <td>
                    <select name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][principle]"
                            style="width:200px;">
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
            <button type="button" class="button fd-kpi-add-approach"
                    data-group="<?php echo esc_attr( $g_idx ); ?>"
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
            <button type="button" class="button fd-kpi-add-kpi"
                    data-group="<?php echo esc_attr( $g_idx ); ?>"
                    style="margin-top:6px;">＋ KPIを追加</button>
        </div>
    </div>
    <?php
}

function fd_kpi_render_approach( $g_idx, $a_idx, $approach, $is_tpl = false ) {
    $val = is_array( $approach ) ? ( $approach['text'] ?? '' ) : $approach;
    ?>
    <div class="fd-approach-row"
         style="display:flex;gap:8px;margin-bottom:6px;align-items:center;">
        <input type="text"
               name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][approaches][<?php echo esc_attr( $a_idx ); ?>][text]"
               value="<?php echo esc_attr( $val ); ?>"
               style="flex:1;" placeholder="取り組み内容を入力"/>
        <button type="button" class="button fd-kpi-remove-approach"
                style="color:#b32d2e;">✕</button>
    </div>
    <?php
}

function fd_kpi_render_kpi_row( $g_idx, $k_idx, $kpi, $kpi_masters, $is_tpl = false ) {
    $selected_id = $kpi['master_id']        ?? '';
    $last_goal   = $kpi['last_year_goal']   ?? '';
    $last_actual = $kpi['last_year_actual'] ?? '';
    $this_goal   = $kpi['this_year_goal']   ?? '';
    ?>
    <div class="fd-kpi-row"
         style="border:1px dashed #ccd0d4;border-radius:4px;padding:10px;margin-bottom:8px;">
        <div style="display:flex;justify-content:flex-end;margin-bottom:6px;">
            <button type="button" class="button fd-kpi-remove-kpi"
                    style="color:#b32d2e;">✕ KPIを削除</button>
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
                <td>
                    <input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][last_year_goal]"
                           value="<?php echo esc_attr( $last_goal ); ?>"
                           style="width:200px;"/>
                </td>
            </tr>
            <tr>
                <th><label>昨年度実績</label></th>
                <td>
                    <input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][last_year_actual]"
                           value="<?php echo esc_attr( $last_actual ); ?>"
                           style="width:200px;"/>
                </td>
            </tr>
            <tr>
                <th><label>本年度目標</label></th>
                <td>
                    <input type="text"
                           name="fd_kpi[groups][<?php echo esc_attr( $g_idx ); ?>][kpis][<?php echo esc_attr( $k_idx ); ?>][this_year_goal]"
                           value="<?php echo esc_attr( $this_goal ); ?>"
                           style="width:200px;"/>
                </td>
            </tr>
        </table>
    </div>
    <?php
}