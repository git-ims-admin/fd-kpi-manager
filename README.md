# FD方針・KPI管理プラグイン — `fd-kpi-manager`

保険代理店HP向け「お客様本位の業務運営（FD）方針」および「KPI」を  
管理・表示するための独自 WordPress プラグインです。  
外部プラグイン（CPTUI / ACF Pro など）への依存は一切ありません。

---

## ディレクトリ構成

```
fd-kpi-manager/
├── fd-kpi-manager.php   # メインファイル（全ロジック）
├── assets/
│   ├── admin.js         # 繰り返しフィールド制御（jQuery）
│   ├── admin.css        # 管理画面スタイル
│   └── frontend.css     # フロントエンドスタイル
└── README.md
```

---

## インストール手順

1. `fd-kpi-manager` フォルダごと `/wp-content/plugins/` にアップロード。
2. WordPress 管理画面 › プラグイン › **有効化**。

---

## 使い方

### 1. KPIマスタの登録

管理画面左メニューに「**KPIマスタ**」が追加されます。  
「新規追加」からKPI評価項目名（例：アンケート回収率）を登録してください。  
ここに登録した項目が、投稿編集画面のセレクトボックスに表示されます。

### 2. 投稿へのFD方針・KPI入力

通常の「投稿」編集画面を開くと、**「FD方針・KPI 入力フォーム」** メタボックスが表示されます。

| 項目 | 説明 |
|------|------|
| 冒頭文 | ページ先頭に表示される自由文（改行OK） |
| リンクURL | 金融庁原則ページへの外部URL |
| グループ（方針） | 「＋ グループを追加」で追加。各グループに見出し・原則・取り組み・KPIを登録 |

各グループ内の「＋ 取り組みを追加」「＋ KPIを追加」ボタンで  
複数の取り組み・KPIを紐付けられます。

### 3. フロントエンド表示

投稿を公開すると、本文の**直前**に FD方針・KPI コンテンツが自動挿入されます。

任意の投稿・固定ページで使いたい場合はショートコードも利用可能です：

```
[fd_kpi]                    ← 現在の投稿IDを使用
[fd_kpi id="123"]           ← 投稿ID を指定
```

---

## 出力構造（フロントエンド）

```
<p class="fd-kpi-intro">      冒頭文
<p class="fd-kpi-notice">     金融庁原則への固定文

<h2 class="is-style-vk-heading-primary">  方針1 見出し（原則X）
  <h3>具体的な取り組み</h3>
  <ul class="fd-kpi-approach-list">  取り組みリスト（・記号）
  <h3>KPI</h3>
  <table class="fd-kpi-table">  方針別KPI表

（方針2、方針3… 繰り返し）

<h2 class="is-style-vk-heading-primary">  KPIの公表
<p>  固定文
<table class="fd-kpi-table">  全KPI集約テーブル
```

---

## VK Blocks との連携

出力される見出しには `is-style-vk-heading-primary` / `is-style-vk-heading-secondary`  
クラスが付与されます。VK Blocks を有効にしているテーマでは、  
ブロックエディタの見出しと同じスタイルが自動適用されます。

---

## データ保存仕様

| キー | 型 | 内容 |
|------|----|------|
| `_fd_kpi_data` | シリアライズ配列 | 全入力データを1つのポストメタに保存 |

配列構造：
```php
[
  'intro'    => 'string',
  'link_url' => 'url string',
  'groups'   => [
    [
      'heading'    => 'string',
      'principle'  => '原則X',
      'approaches' => [ ['text'=>'string'], ... ],
      'kpis'       => [
        [
          'master_id'        => int,
          'last_year_goal'   => 'string',
          'last_year_actual' => 'string',
          'this_year_goal'   => 'string',
        ],
        ...
      ],
    ],
    ...
  ],
]
```

---

## セキュリティ

- 保存時に `wp_verify_nonce` でリクエストを検証。
- 文字列は `sanitize_text_field` / `sanitize_textarea_field` でサニタイズ。
- URL は `esc_url_raw` でサニタイズ、出力時は `esc_url`。
- 出力は `esc_html` / `nl2br` で XSS を防止。

---

## 動作要件

- WordPress 5.8 以上
- PHP 7.4 以上
