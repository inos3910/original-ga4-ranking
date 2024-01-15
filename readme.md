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

### 1, composer で必要ファイルをインストール

動かしたい環境にファイル一式を設置して下記コマンド使う。<br>
`composer install`

### 2, プラグインを有効化

インストールして有効化すると、メニューに「人気記事（GA4）設定」という項目が追加される

### 3, 管理画面で各種設定

「人気記事（GA4）設定」から各種設定を保存

- JSON キー：GCP サービスアカウントを作成してキーを取得する
- GA4 プロパティ ID：対象の GA4 アカウントで確認
- URL フィルター：絞り込みたい URL を追加する。/blog と設定した場合は/blog 以下の記事で人気ランキングを取得する

### 4, コーディングする

```
<?php
use Original\Ga4\Ranking\Ga4RankingPlugin;


if (!class_exists('Original\Ga4\Ranking\Ga4RankingPlugin')) {
  return;
}

//10件表示する
$limit = 10;

//ランキング取得（引数に記事数を入れる）
$popular_query = Ga4RankingPlugin::get_ranking_data($limit);
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
endforeach;
```

##参考 URL

サービスアカウントの取得方法は下記でわかりやすく解説されていたものを勉強させていただきました。

- https://tech.excite.co.jp/entry/2023/04/11/104500
- https://twinkangaroos.com/how-to-run-google-analytics-data-api-ga4-with-php.html
