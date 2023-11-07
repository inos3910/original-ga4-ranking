# WordPress プラグイン Original GA4 Ranking

## バージョン

v1.0 （2023-11-07）

## 概要

- GA4 の API を使って WordPress の人気記事ランキングを取得するプラグイン
- composer で PHP 用のクライアントライブラリをインストールし、API から記事取得する関数を定義
- 管理画面に必要項目を入力すればすぐに使える
- API から取得したデータは WP の Transient API で db にキャッシュさせている
- BASIC 認証がかかっているサイトでは使えない
- 対象期間は 30 日間（※変更機能を検討中）
- 一度の取得件数は 1000 件（※変更機能を検討中）

## 動作環境

PHP8 以上（8.2 で動作確認済み）<br>
PHP7 で動かしたい場合は composer.json で php のバージョンを指定して update する必要あり。

## 主な使い方

### 1, プラグインを有効化

インストールして有効化すると、メニューに「人気記事（GA4）設定」という項目が追加される

### 2, 管理画面で各種設定

「人気記事（GA4）設定」から各種設定を保存

- JSON キー：GCP サービスアカウントを作成してキーを取得する
- GA4 プロパティ ID：対象の GA4 アカウントで確認
- URL フィルター：絞り込みたい URL を追加する。/blog と設定した場合は/blog 以下の記事で人気ランキングを取得する

### 3, コーディングする

```
<?php
if (!class_exists('Original_GA4_Ranking')) {
  return;
}

//10件表示する例
$limit = 10;
$popular_query = Original_GA4_Ranking::get_ranking_data();
if (empty($popular_query)) {
  return;
}

foreach ((array)$popular_query as $article) :
  //post_idが空の場合はスキップ
  if (empty($article['post_id'])) {
    continue;
  }

  //記事が見つからない場合はスキップ
  $post = get_post($article['post_id']);
  if (empty($post)) {
    continue;
  }

  //記事がある場合はWP_Postオブジェクトでゴニョゴニョ
  var_dump($post);
  //PV数
  var_dump($article['pv']);
  //URL
  var_dump($article['url']);
  //ページのパス
  var_dump($article['page_path']);

  //表示上限処理
  --$limit;
  if ($limit < 1) {
    break;
  }
endforeach;
```