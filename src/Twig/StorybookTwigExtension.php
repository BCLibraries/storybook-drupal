<?php
namespace Drupal\storybook\Twig;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Theme\ComponentPluginManager;
use Twig\Extension\AbstractExtension;
use Twig\Environment;
use Twig\TwigFunction;

/**
 * The twig extension so we can include an individual story from a collection of stories.
 */
class StorybookTwigExtension extends AbstractExtension {

  /**
   * @var \Twig\Environment
   *  The twig environment.
   */
  protected Environment $environment;

  /**
   * @var \Drupal\Core\Theme\ComponentPluginManager
   *  The component plugin manager.
   */
  protected ComponentPluginManager $componentPluginManager;

  /**
   * @var \Drupal\Core\Extension\ThemeExtensionList
   *  The theme list.
   */
  protected ThemeExtensionList $themeList;

  /**
   * @var \Drupal\Core\Extension\ModuleExtensionList
   *  The module list.
   */
  protected ModuleExtensionList $moduleList;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Theme\ComponentPluginManager $componentPluginManager
   *   The component plugin manager.
   * @param \Twig\Environment $environment
   *   The twig environment.
   * @param \Drupal\Core\Extension\ThemeExtensionList $themeList
   *  The theme list.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleList
   * The module list.
   */
  public function __construct(ComponentPluginManager $componentPluginManager, Environment $environment, ThemeExtensionList $themeList, ModuleExtensionList $moduleList) {
    $this->environment = $environment;
    $this->componentPluginManager = $componentPluginManager;
    $this->themeList = $themeList;
    $this->moduleList = $moduleList;
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    return [
      new TwigFunction('include_story', [$this, 'renderStoryFunction'], ['is_safe' => ['html']]),
    ];
  }

  /**
   * Render a story from a component.
   *
   * @param string $stories_name
   *   The name of the component. <module-or-theme>:<component>.
   * @param string $story_id
   *   The ID of the story.
   * @param array $context
   *  The context to pass to the story.
 */
  public function renderStoryFunction(string $stories_name, string $story_id, array $context = []) {
    // $stories_name will be theme-or-module:component get the theme or module name and get all the stories.twig that match the component name.
    [$extension_name, $component] = explode(':', $stories_name);


    // build up component.stories.twig name
    $filename = $component . '.stories.twig';

    // get the path of the theme or module
    $extension_path = $this->negotiatePath($extension_name);

    $template_path = $this->findFileInDirectory($extension_path, $filename);

    if ($template_path === null) {
      // File found, do something with $found_path
      return "File not found";
    }

    // @todo need to check if the story_id is valid
//    $this->validateStoryId($story_id, $template_path);

    // Prepare the context, passing the story ID as '_story' and merging additional args.
    $context = array_merge(['_story' => $story_id], $context);
    // need to try and load and render below but if there is an error print out the error message instead of throwing an exception
    try {
      return $this->environment->load($template_path)->render($context);
    } catch (\Exception $e) {
      return $e->getMessage();
    }
  }

  private function negotiatePath($extension_name) {
    // get the path of the theme or module
    $theme_extension_exists = $this->themeList->exists($extension_name);
    $module_extension_exists = $this->moduleList->exists($extension_name);

    if ($theme_extension_exists) {
      return $this->themeList->getPath($extension_name);
    } elseif ($module_extension_exists) {
      return $this->moduleList->getPath($extension_name);
    } else {
      return "Theme or module not found";
    }
  }

  /**
   * Find a file in a directory.
   *
   * @param string $directory
   *   The directory to search in.
   * @param string $filename
   *   The filename to search for.
   *
   * @return string|null
   *   The path to the file if found, otherwise null.
   */
  private function findFileInDirectory(string $directory, string $filename): ?string
  {
    // look only in the components directory.
    $directory .= '/components';
    $iterator = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
      if ($file->getFilename() === $filename) {
        return $file->getPathname();
      }
    }

    // Return null if the file was not found
    return null;
  }

  public function getName() {
    return 'storybook_twig_extension';
  }

}
