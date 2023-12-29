<?php

namespace Drupal\sdc_storybook\Twig\tag;

use Twig\Error\SyntaxError;
use Twig\Node\Expression\ConstantExpression;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses the template syntax.
 */
final class StoryTokenParser extends AbstractTokenParser {

  /**
   * Opening twig tag.
   *
   * @var string
   */
  private string $tagName;

  /**
   * Opening twig tag.
   *
   * @var string
   */
  private string $endTag;

  /**
   * Creates a new ComponentTokenParser.
   *
   * @param string $tag_name
   *   The name of the tag.
   */
  public function __construct(string $tag_name = 'story') {
    $this->tagName = $tag_name;
    $this->endTag = sprintf('end%s', $tag_name);
  }

  /**
   * Parses the Component token.
   *
   * @param \Twig\Token $token
   *   The token.
   *
   * @return StoryNode
   *   The node.
   *
   * @throws \Twig\Error\SyntaxError
   */
  public function parse(Token $token): StoryNode {
    $lineno = $token->getLine();
    $stream = $this->parser->getStream();
    // Recovers all inline parameters close to your tag name.
    [$story_id, $story_meta] = $this->parseArguments();
    $this->parser->pushLocalScope();

    $continue = TRUE;
    $story_template = NULL;
    while ($continue) {
      // Create subtree until the decideComponentFork() callback returns true.
      $story_template = $this->parser->subparse(
        fn (Token $token) => $token->test([$this->endTag])
      );

      // I like to put a switch here, in case you need to add middle tags, such
      // as: {% story %}, {% endstory %}.
      $tag = $stream->next()->getValue();

      $continue = match ($tag) {
        $this->endTag => FALSE,
        default => throw new SyntaxError(sprintf('Unexpected end of template. Twig was looking for the following tags "endstory" to close the "story" block started at line %d)', $lineno), -1),
      };
      $this->parser->popLocalScope();

      // Parse the {% endstory %}.
      $this->parseEndComponentName($token);
    }

    return new StoryNode(
      $story_id,
      $story_meta,
      $story_template,
      $lineno,
      $this->getTag()
    );
  }

  /**
   * Parse the endstory name.
   *
   * @param \Twig\Token $token
   *   The token.
   *
   * @throws \Twig\Error\SyntaxError
   */
  protected function parseEndComponentName(Token $token): void {
    $stream = $this->parser->getStream();
    $stream->expect(Token::BLOCK_END_TYPE);
  }

  /**
   * Parses the arguments.
   *
   * This extracts the story ID, and story metadata from:
   * {% story 'Default' using { foo: 'bar' } %}
   *
   * @return array
   *   The parsed arguments.
   *
   * @throws \Twig\Error\SyntaxError
   */
  protected function parseArguments(): array {
    $stream = $this->parser->getStream();

    $story_meta = [];

    $id = $this->parser->getExpressionParser()->parseStringExpression();
    if ($stream->nextIf(Token::NAME_TYPE, 'using')) {
      $story_meta = $this->parser->getExpressionParser()->parseExpression();
    }

    $stream->expect(Token::BLOCK_END_TYPE);
    return [$id, $story_meta];
  }

  /**
   * Get the tag name.
   *
   * @return string
   *   The tag name.
   */
  public function getTag(): string {
    return $this->tagName;
  }

}
