<?php

/**
 * Plugin Name: Original GA4 Ranking
 * Plugin URI: https://github.com/inos3910/original-ga4-ranking
 * Update URI: https://github.com/inos3910/original-ga4-ranking
 * Description: GA4人気記事ランキング取得プラグイン
 * Author: SHARESL
 * Author URI: https://sharesl.net/
 * Version: 1.0
 */

namespace Sharesl\Original\Ga4\Ranking;

if (!defined('ABSPATH')) {
  exit;
}

use \Google\Analytics\Data\V1beta\BetaAnalyticsDataClient;
use \Google\Analytics\Data\V1beta\DateRange;
use \Google\Analytics\Data\V1beta\Dimension;
use \Google\Analytics\Data\V1beta\FilterExpression;
use \Google\Analytics\Data\V1beta\Filter;
use \Google\Analytics\Data\V1beta\Filter\StringFilter;
use \Google\Analytics\Data\V1beta\Filter\StringFilter\MatchType;
use \Google\Analytics\Data\V1beta\Metric;
use \Google\ApiCore\ApiException;

class Ga4RankingPlugin
{
  public function __construct()
  {
    add_action('plugins_loaded', [$this, 'plugins_loaded']);
  }

  /**
   * 初期化
   */
  public function plugins_loaded()
  {
    add_action('admin_menu', [$this, 'add_menu']);
    add_action('admin_init', [$this, 'register_settings']);
    add_action('transition_post_status', [$this, 'transition_post_status'], 10, 3);
  }

  public function transition_post_status($new_status, $old_status, $post)
  {
    $statuses = ['private', 'trash', 'draft'];
    if (in_array($new_status, $statuses, true)) {
      $cache_prefix = 'original_ga4_ranking_data_';
      $this->delete_transient_prefix($cache_prefix);
    }
  }

  //GA4メニュー追加
  public function add_menu()
  {
    add_menu_page(
      '人気記事（GA4）設定',
      '人気記事（GA4）設定',
      'manage_options',
      'original_ga4_ranking',
      [$this, 'setting_page_html'],
      'dashicons-admin-settings',
      5
    );
  }

  //GA4ランキング設定画面 フィールドの登録処理
  public function register_settings()
  {
    register_setting('ga4-ranking-settings-group', 'ga4_ranking_credentials');
    register_setting('ga4-ranking-settings-group', 'ga4_ranking_property_id');
    register_setting('ga4-ranking-settings-group', 'ga4_ranking_dimension_filter');

    // セクションを追加
    add_settings_section(
      'ga4_ranking_section',
      '',
      [],
      'ga4-ranking-settings-group'
    );

    add_settings_field(
      'ga4_ranking_credentials',
      'JSONキー',
      [$this, 'render_credentials_field'],
      'ga4-ranking-settings-group',
      "ga4_ranking_section",
      ['label_for' => 'ga4_ranking_credentials']
    );

    add_settings_field(
      'ga4_ranking_property_id',
      'GA4プロパティID',
      [$this, 'render_property_id_field'],
      'ga4-ranking-settings-group',
      "ga4_ranking_section",
      ['label_for' => 'ga4_ranking_property_id']
    );

    add_settings_field(
      'ga4_ranking_dimension_filter',
      'URLフィルター',
      [$this, 'render_url_filter_field'],
      'ga4-ranking-settings-group',
      "ga4_ranking_section",
      ['label_for' => 'ga4_ranking_dimension_filter']
    );
  }

  public function render_credentials_field()
  { ?>
    <p>
      <textarea class="large-text code" id="ga4_ranking_credentials" name="ga4_ranking_credentials" cols="160" rows="7"><?php echo esc_attr(get_option('ga4_ranking_credentials')); ?></textarea>
    </p>
    <p class="description">GCP→サービスアカウントから取得</p>
  <?php
  }

  public function render_property_id_field()
  { ?>
    <p>
      <input class="regular-text code" type="text" id="ga4_ranking_property_id" name="ga4_ranking_property_id" value="<?php echo esc_attr(get_option('ga4_ranking_property_id')) ?>">
    </p>
    <p class="description">記事を取得したいGA4プロパティのID</p>
  <?php
  }

  public function render_url_filter_field()
  { ?>
    <p>
      <input class="regular-text code" type="text" id="ga4_ranking_dimension_filter" name="ga4_ranking_dimension_filter" value="<?php echo esc_attr(get_option('ga4_ranking_dimension_filter')) ?>">
    </p>
    <p class="description">/blog/・/article/ など記事を取得したいディレクトリを絞り込む。</p>
  <?php
  }

  // 特定のprefixが付いたTransient API Cacheのクリア
  private function delete_transient_prefix($prefix)
  {
    global $wpdb;
    $db_prefix = $wpdb->prefix;
    $wpdb->query("delete from `{$db_prefix}options` where `option_name` like '%_transient_{$prefix}_%'");
  }

  //設定画面のソース
  public function setting_page_html()
  {
    //キャッシュクリア設定
    $is_clear_cache = filter_input(INPUT_POST, 'clear_ga4_ranking_cache', FILTER_SANITIZE_NUMBER_INT);
  ?>
    <div class="wrap">
      <h1>人気記事（GA4）設定</h1>
      <?php
      if ($is_clear_cache == 1) {
        $cache_prefix = 'original_ga4_ranking_data_';
        $this->delete_transient_prefix($cache_prefix);
      ?>
        <div class='updated notice notice-success'>
          <p>キャッシュをクリアしました</p>
        </div>
      <?php
      }
      ?>
      <div class="admin_optional">
        <form method="post" action="options.php">
          <?php settings_fields('ga4-ranking-settings-group'); ?>
          <?php do_settings_sections('ga4-ranking-settings-group'); ?>
          <?php submit_button(); ?>
        </form>
        <hr>
        <table class="form-table">
          <tr>
            <th scope="row">キャッシュ</th>
            <td>
              <form method="post" action="">
                <p>
                  <button type="submit" class="button button-secondary" name="clear_ga4_ranking_cache" value="1">キャッシュをクリア</button>
                </p>
                <p class="description">キャッシュをクリアして次回表示時に最新の情報を取得します。</p>
              </form>
            </td>
          </tr>
        </table>
      </div>
    </div>
<?php
  }

  //ランキング取得関数
  public static function get_ranking_data($limit = 1000)
  {

    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

    //Transient cache
    $transient_name = "original_ga4_ranking_data_{$limit}";
    $rank_data      = get_transient($transient_name);

    //キャッシュありの場合
    if (!empty($rank_data)) {
      return $rank_data;
    }


    //キャッシュなしの場合
    $rank_data = [];

    $json                 = get_option('ga4_ranking_credentials');
    $property_id          = get_option('ga4_ranking_property_id');
    $dimension_filter_dir = get_option('ga4_ranking_dimension_filter');

    if (empty($json) || empty($property_id)) {
      return $rank_data;
    }

    $credentials = json_decode($json, true);

    try {
      $client = new BetaAnalyticsDataClient([
        'credentials' => $credentials,
      ]);

      // API取得処理
      $response = $client->runReport([
        'property'   => 'properties/' . $property_id,
        'limit'      => 1000,
        'dateRanges' => [
          new DateRange([
            'start_date' => '30daysAgo',
            'end_date' => 'yesterday',
          ]),
        ],
        'dimensions' => [
          new Dimension([
            'name' => 'pagePath',
          ]),
        ],
        'dimensionFilter' =>
        !empty($dimension_filter_dir) ? new FilterExpression([
          'filter' => new Filter([
            'field_name' => 'pagePath',
            'string_filter' => new StringFilter([
              'match_type' => MatchType::PARTIAL_REGEXP,
              'value' => $dimension_filter_dir . '[^\?]+'
            ]),
          ]),
        ]) : [],
        'metrics' => [
          new Metric([
            'name' => 'screenPageViews',
          ]),
        ],
      ]);
    }
    //例外処理
    catch (ApiException $e) {
      return $rank_data;
    } finally {
      if (empty($response)) {
        return $rank_data;
      }

      foreach ($response->getRows() as $row) {
        $dimension_values = $row->getDimensionValues();
        if (empty($dimension_values)) {
          continue;
        }

        $metric_values = $row->getMetricValues();
        if (empty($metric_values)) {
          continue;
        }

        //パスから投稿IDを取得
        $page_path   = $dimension_values[0]->getValue();
        $post_id     = url_to_postid($page_path);
        if (empty($post_id)) {
          continue;
        }

        //投稿がない・もしくはステータスが公開ではない場合にはスキップ
        $post = get_post($post_id);
        if (empty($post) ||  $post->post_status !== 'publish') {
          continue;
        }

        $pv          = $metric_values[0]->getValue();
        $url         = get_permalink($post_id);

        $rank_data[] = [
          'page_path' => $page_path,
          'pv'        => $pv,
          'post_id'   => $post_id,
          'url'       => $url
        ];

        --$limit;
        if ($limit < 1) {
          break;
        }
      }

      set_transient($transient_name, $rank_data, 12 * HOUR_IN_SECONDS);

      return $rank_data;
    }
  }
}

new Ga4RankingPlugin();
