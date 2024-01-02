<?php

namespace Drupal\twig_storybook\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\JsonResponse;
use TwigStorybook\Exception\StoryRenderException;
use TwigStorybook\Service\StoryRenderer;

/**
 * An endpoint for the Storybook integration.
 */
final class ServerController extends ControllerBase {

  public function generateStories() {
    $renderer = \Drupal::service(StoryRenderer::class);
    $data = $renderer->generateStoriesJsonFile(
      '@twig_storybook/test_syntax.stories.twig',
      Url::fromUri('internal:/storybook/story/render', ['absolute' => TRUE])
        ->toString(TRUE)
        ->getGeneratedUrl()
    );
    return new JsonResponse($data);
  }

  public function renderStory(string $hash): array {
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
    $story_name = $decoded['name'] ?? '';
    if (empty($template_path) || empty($story_name)) {
      throw new StoryRenderException('Impossible to locate a story to render without the template path or the story name.');
    }
    return [
      '#type' => 'container',
      '#attributes' => ['id' => '___storybook_wrapper'],
      'template' => [
        '#type' => 'inline_template',
        '#template' => sprintf("{{ include('%s', { _story: '%s' }, with_context = false) }}", $template_path, $story_name),
      ],
    ];
  }

}
