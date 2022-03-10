<?php

namespace Drupal\illinois_framework_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_remote\Plugin\Field\FieldFormatter\MediaRemoteFormatterBase;

/**
 * Plugin implementation of the 'media_remote_illinois_mediaspace' formatter.
 *
 * @FieldFormatter(
 *   id = "media_remote_illinois_mediaspace",
 *   label = @Translation("Remote Media - MediaSpace"),
 *   field_types = {
 *     "string"
 *   },
 *   provider = "media_remote"
 * )
 */
class MediaRemoteMediaSpaceFormatter extends MediaRemoteFormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function getUrlRegexPattern() {
    return '/^https?:\/\/mediaspace\.illinois\.edu\/media\/([^\/]+)\/(?<id>[^\/]+)\/?(.*)/';
  }

  /**
   * {@inheritdoc}
   */
  public static function getValidUrlExampleStrings(): array {
    return [
      'https://mediaspace.illinois.edu/media/t/[video-id]',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function deriveMediaDefaultNameFromUrl($url) {
    $matches = [];
    $pattern = static::getUrlRegexPattern();
    preg_match_all($pattern, $url, $matches);
    if (!empty($matches[1][0])) {
      return t('MediaSpace video from @url', [
        '@url' => $url,
      ]);
    }
    return parent::deriveMediaDefaultNameFromUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Field\FieldItemInterface $item */
      if ($item->isEmpty()) {
        continue;
      }
      $matches = [];
      $pattern = static::getUrlRegexPattern();
      preg_match_all($pattern, $item->value, $matches);
      if (empty($matches[1][0])) {
        continue;
      }
      $elements[$delta] = [
        '#theme' => 'media_remote_illinois_mediaspace',
        '#url' => $this->buildKalturaUrl($item->value),
        '#width' => $this->getSetting('width') ?? 960,
        '#height' => $this->getSetting('height') ?? 600,
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
        'width' => 960,
        'height' => 600,
      ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return parent::settingsForm($form, $form_state) + [
        'width' => [
          '#type' => 'number',
          '#title' => $this->t('Width'),
          '#default_value' => $this->getSetting('width'),
          '#size' => 5,
          '#maxlength' => 5,
          '#field_suffix' => $this->t('pixels'),
          '#min' => 50,
        ],
        'height' => [
          '#type' => 'number',
          '#title' => $this->t('Height'),
          '#default_value' => $this->getSetting('height'),
          '#size' => 5,
          '#maxlength' => 5,
          '#field_suffix' => $this->t('pixels'),
          '#min' => 50,
        ],
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Iframe size: %width x %height pixels', [
      '%width' => $this->getSetting('width'),
      '%height' => $this->getSetting('height'),
    ]);
    return $summary;
  }
  /**
   * {@inheritdoc}
   */
  public static function getIdFromInput($input) {
    preg_match('/^https?:\/\/mediaspace\.illinois\.edu\/media\/([^\/]+)\/(?<id>[^\/]+)\/?(.*)/', $input, $matches);
    return isset($matches['id']) ? $matches['id'] : FALSE;
  }
  /**
   * {@inheritdoc}
   */
  public function buildKalturaUrl($url){
    $id = $this->getIdFromInput($url);
    if($id){
    return "https://mediaspace.illinois.edu/embed/secure/iframe/entryId/".$id."/uiConfId/26883701?streamerType=auto&amp;localizationCode=en&amp;leadWithHTML5=true&amp;flashvars%5BautoPlay%5D=false&amp;flashvars%5BsideBarContainer.plugin%5D=true&amp;flashvars%5BsideBarContainer.position%5D=left&amp;flashvars%5BsideBarContainer.clickToClose%5D=true&amp;flashvars%5Bchapters.plugin%5D=true&amp;flashvars%5Bchapters.layout%5D=vertical&amp;flashvars%5Bchapters.thumbnailRotator%5D=false&amp;flashvars%5BstreamSelector.plugin%5D=true&amp;flashvars%5BEmbedPlayer.SpinnerTarget%5D=videoHolder&amp;flashvars%5BdualScreen.plugin%5D=true&amp;flashvars%5BkmsHistory.plugin%5D=1&amp;flashvars%5BkmsHistory.playbackContext%5D=embed&amp;flashvars%5BkmsHistory.proxyUrl%5D=https%3A%2F%2Fasync-api.kaltura.com%2Fapi_v3%2F&amp;flashvars%5BkmsHistory.iframeHTML5Js%5D=https%3A%2F%2Fkms-a.akamaihd.net%2Fdc-1%2F5.63.15%2Fpublic%2Fbuild0%2Fhistory%2Fasset%2FkmsHistory.js&amp;flashvars%5BkmsHistory.playbackCompletePercent%5D=90&amp;flashvars%5BkmsHistory.playbackCompleteSeconds%5D=10";
    }
    return FALSE;
  }
}
