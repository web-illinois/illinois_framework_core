<?php

namespace Drupal\illinois_framework_core\Plugin\video_embed_field\Provider;

use Drupal\video_embed_field\ProviderPluginBase;

/**
 * An Illinois Media Space provider plugin.
 *
 * @VideoEmbedProvider(
 *   id = "mediaspace",
 *   title = @Translation("Media Space")
 * )
 */
class MediaSpace extends ProviderPluginBase {

  /**
   * {@inheritdoc}
   */
  public function renderEmbedCode($width, $height, $autoplay) {
    $embed_code = [
      '#type' => 'video_embed_iframe',
      '#provider' => 'mediaspace',
      '#url' => sprintf('https://mediaspace.illinois.edu/embed/secure/iframe/entryId/%s/uiConfId/26883701', $this->getVideoId()),
      '#query' => [
        'streamerType' => 'auto',
        'localizationCode' => 'en',
        'leadWithHTML5' => 'true',
        'flashvars[autoPlay]' => (boolval($autoplay) ? 'true' : 'false'),
        'flashvars[sideBarContainer.plugin]' => 'true',
        'flashvars[sideBarContainer.position]' => 'left',
        'flashvars[sideBarContainer.clickToClose]' => 'true',
        'flashvars[chapters.plugin]' => 'true',
        'flashvars[chapters.layout]' => 'vertical',
        'flashvars[chapters.thumbnailRotator]' => 'false',
        'flashvars[streamSelector.plugin]' => 'true',
        'flashvars[EmbedPlayer.SpinnerTarget]' => 'videoHolder',
        'flashvars[dualScreen.plugin]' => 'true',
        'flashvars[kmsHistory.plugin]' => '1',
        'flashvars[kmsHistory.playbackContext]' => 'embed',
        'flashvars[kmsHistory.proxyUrl]' => 'https://async-api.kaltura.com/api_v3/',
        'flashvars[kmsHistory.iframeHTML5Js]' => 'https://kms-a.akamaihd.net/dc-1/5.63.15/public/build0/history/asset/kmsHistory.js',
        'flashvars[kmsHistory.playbackCompletePercent]' => '90',
        'flashvars[kmsHistory.playbackCompleteSeconds]' => '10'
      ],
      '#attributes' => [
        'width' => $width,
        'height' => $height,
        'frameborder' => '0',
        'class', 'kmsembed',
        'allowfullscreen' => 'allowfullscreen',
      ],
    ];

    return $embed_code;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteThumbnailUrl() {
    $url = 'http://cdnsecakmi.kaltura.com/p/1329972/sp/132997200/thumbnail/entry_id/%s/width/500.jpg';
    $thumbnail = sprintf($url, $this->getVideoId());
    return $thumbnail;
  }

  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    preg_match('/^https?:\/\/mediaspace\.illinois\.edu\/media\/([^\/]+)\/(?<id>[^\/]+)\/?(.*)/', $input, $matches);
    return isset($matches['id']) ? $matches['id'] : FALSE;
  }

}
