<?php

namespace Drupal\storybook\Controller;

use Drupal\Core\Url;
use Drupal\sdc\Plugin\Component;
use Drupal\sdc\Component\ComponentMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\State\StateInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use TwigStorybook\Exception\StoryRenderException;
use TwigStorybook\Service\StoryRenderer;

/**
 * Provides an endpoint for Storybook to query.
 *
 * @see https://github.com/storybookjs/storybook/tree/next/app/server
 */
class ServerController extends ControllerBase {

  /**
   * Kill-switch to avoid caching the page.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  private KillSwitch $cacheKillSwitch;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

  /**
   * Indicates if the site is operating in development mode.
   *
   * @var bool
   */
  private bool $developmentMode;

  /**
   * The story renderer.
   *
   * @var \TwigStorybook\Service\StoryRenderer
   */
  private StoryRenderer $storyRenderer;

  /**
   * Creates an object.
   *
   * @param \Drupal\Core\PageCache\ResponsePolicy\KillSwitch $cache_kill_switch
   *   The cache kill switch.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(KillSwitch $cache_kill_switch, StateInterface $state, TimeInterface $time, StoryRenderer $story_renderer, bool $development_mode) {
    $this->cacheKillSwitch = $cache_kill_switch;
    $this->state = $state;
    $this->time = $time;
    $this->storyRenderer = $story_renderer;
    $this->developmentMode = $development_mode;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $cache_kill_switch = $container->get('page_cache_kill_switch');
    assert($cache_kill_switch instanceof KillSwitch);
    $state = $container->get('state');
    assert($state instanceof StateInterface);
    $time = $container->get('datetime.time');
    assert($time instanceof TimeInterface);
    $story_renderer = $container->get(StoryRenderer::class);
    assert($story_renderer instanceof StoryRenderer);
    $development_mode = (bool) $container->getParameter('storybook.development');
    return new static($cache_kill_switch, $state, $time, $story_renderer, $development_mode);
  }

  public function generateStories() {
    $data = $this->storyRenderer->generateStoriesJsonFile(
      'modules/contrib/sdc_examples/components/my-card/my-card.stories.twig',
      Url::fromUri('internal:/storybook/stories/render', ['absolute' => TRUE])
        ->toString(TRUE)
        ->getGeneratedUrl()
    );
    return new JsonResponse($data);
  }

  public function renderStory(string $hash, Request $request): array {
    try {
      $decoded = json_decode(
        base64_decode($hash),
        TRUE,
        512,
        JSON_THROW_ON_ERROR,
      );
    }
    catch (\JsonException $e) {
      throw new StoryRenderException('Unable to decode the story ID. Avoid tampering with the generated URL.', previous: $e);
    }
    $template_path = $decoded['path'] ?? '';
    $story_id = $decoded['id'] ?? '';
    if (empty($template_path) || empty($story_id)) {
      throw new StoryRenderException('Impossible to locate a story to render without the template path or the story name.');
    }
    if ($this->developmentMode) {
      $this->cacheKillSwitch->trigger();
      // Replace with the 'asset.query_string' service in drupal:^10.2.0.
      // @see https://www.drupal.org/node/3358337
      $query_string = base_convert(strval($this->time->getRequestTime()), 10, 36);
      $this->state->setMultiple([
        'system.css_js_query_string' => $query_string,
        'asset.css_js_query_string' => $query_string,
      ]);
    }
    $arguments = $this->getArguments($request);
    return [
      '#attached' => ['library' => ['storybook/attach_behaviors']],
      '#type' => 'container',
      '#cache' => $this->developmentMode ? ['max-age' => 0] : [],
      '#attributes' => ['id' => '___storybook_wrapper'],
      'template' => [
        '#type' => 'inline_template',
        '#template' => sprintf("{{ include('%s') }}", $template_path),
        '#context' => [
          '_story' => $story_id,
        ]
      ],
    ];
  }

  /**
   * Gets the arguments.
   *
   * Retrieve the arguments from the query string if the request is a GET. If
   * the request is a POST, retrieve the arguments from the request body.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The inbound request.
   *
   * @return array
   *   The array of arguments.
   */
  private function getArguments(Request $request): array {
    $json = '[]';
    if ($request->getMethod() === 'GET') {
      $json = base64_decode($request->query->get('_params', ''), TRUE);
    }
    if ($request->getMethod() === 'POST') {
      $json = $request->getContent();
    }
    $args = Json::decode($json ?: '[]');
    return is_array($args) ? $args : [];
  }

}
