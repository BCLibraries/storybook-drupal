<?php

namespace Drupal\sdc_storybook\Twig\tag;

use Drupal\sdc_storybook\Exception\StorySyntaxException;
use Twig\Compiler;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Node;
use Twig\Node\NodeOutputInterface;
use Drupal\sdc_storybook\Twig\TwigExtension;

/**
 * Generates the PHP code from the parsed template.
 */
class StoryNode extends Node implements NodeOutputInterface {

  /**
   * ComponentNode constructor.
   *
   * @param \Twig\Node\Expression\ConstantExpression $story_id
   *   The story ID.
   * @param \Twig\Node\Expression\AbstractExpression|null $story_meta
   *   The metadata for the story.
   * @param \Twig\Node\Node|null $story_template
   *   The template.
   * @param int $lineno
   *   The line number.
   * @param string|null $tag
   *   The tag name.
   */
  public function __construct(
    AbstractExpression $story_id,
    ?AbstractExpression $story_meta,
    ?Node $story_template,
    int $lineno,
    string $tag = NULL
  ) {
    $nodes = ['story_id' => $story_id];

    if ($story_meta !== NULL) {
      $nodes['story_meta'] = $story_meta;
    }

    $nodes['story_template'] = $story_template;

    parent::__construct($nodes, [], $lineno, $tag);
  }

  /**
   * Compiles.
   *
   * @param \Twig\Compiler $compiler
   *   The compiler.
   *
   * @throws \Drupal\sdc_storybook\Exception\StorySyntaxException
   */
  public function compile(Compiler $compiler): void {
    $compiler->addDebugInfo($this);
    $nesting_depth = $this->getAttribute('nesting_depth');

    $story_id_node = $this->getNode('story_id');
    if (!$story_id_node instanceof ConstantExpression) {
      throw new StorySyntaxException("Use quoted strings for the {% story 'MyStory' %} tag.");
    }
    $story_id = $story_id_node->getAttribute('value');
    $compiler->raw('if (')
      ->string($story_id)
      ->raw(' !== $context["_story"]) {')
      ->raw(PHP_EOL)
      ->indent()
      ->write('return "";')
      ->raw(PHP_EOL)
      ->outdent()
      ->write('}')
      ->raw(PHP_EOL);

    // Theoretically, we may want to support stories inside of stories. That is
    // why we may each variable an array indexed by the nesting depth.
    if ($nesting_depth > 1) {
      $compiler->write(sprintf(
        '$context = array_merge($context, $_story_meta[%d] ?? []);',
        $nesting_depth - 1
      ))->raw(PHP_EOL);
    }
    // $_story_meta[3] = ['foo' => 'bar'];
    $compiler->write(sprintf('$_story_meta[%s] = ', $nesting_depth));
    $this->hasNode('story_meta')
      ? $compiler->subcompile($this->getNode('story_meta'))
      : $compiler->raw('[]');
    $compiler->write(';')->raw(PHP_EOL);

    if (!$this->hasNode('story_template')) {
      $compiler->write(sprintf('$_story_template[%d] = "";', $nesting_depth));
    }
    elseif ($this->getNode('story_template') instanceof AbstractExpression) {
      $compiler->write(sprintf('$_story_template[%d] = ', $nesting_depth))
        ->subcompile($this->getNode('story_template'))
        ->raw(';')
        ->raw(PHP_EOL);
    }
    else {
      // @todo: Twig is moving away from ob_* to make parallelization possible.
      $compiler->write('ob_start();')->raw(PHP_EOL);
      $compiler->subcompile($this->getNode('story_template'));
      $compiler->write(sprintf('$_story_template[%d] = ob_get_clean();', $nesting_depth))
        ->raw(PHP_EOL);
    }

    // Get the extension.
    $compiler->raw('$extension = $this->extensions[')
      ->string(TwigExtension::class)
      ->write('];')
      ->raw(PHP_EOL);

    // Collect all the stories for the given path, as we process them.
    $path = $this->getSourceContext()?->getPath() ?? '';
    $compiler->raw('$extension->storyCollector->collect(')
      ->string($path)
      ->raw(', ')
      ->string($story_id)
      ->raw(', ')
      ->write(sprintf('$_story_meta[%s]', $nesting_depth))
      ->raw(');')
      ->raw(PHP_EOL);

    // Echo the results of the render.
    $compiler->raw('echo ')
      ->write('$extension->storyRenderer->renderStory(')
      ->string($story_id)
      ->raw(sprintf(', $_story_meta[%d]', $nesting_depth))
      ->raw(sprintf(', $_story_template[%d]', $nesting_depth))
      ->raw(', $context')
      ->raw(');')
      ->raw(PHP_EOL);
  }

}
