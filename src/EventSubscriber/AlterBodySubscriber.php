<?php

namespace Drupal\sdc_storybook\EventSubscriber;

use Drupal\sdc_storybook\Util;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;

class AlterBodySubscriber implements EventSubscriberInterface {

  /**
   * Remove the X-Frame-Options header from the response for our route.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The event to process.
   */
  public function alter(ResponseEvent $event) {
    if (!Util::isRenderController($event->getRequest())) {
      return;
    }
    $response = $event->getResponse();
    $html = $response->getContent();
    $dom = new \DOMDocument();
    $dom->loadHTML($html);
    $crawler = new Crawler($dom);
    $wrapper_contents = $crawler->filter('#___storybook_wrapper *');
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body || $wrapper_contents->count() === 0) {
      throw new HttpException(500, 'Unable to process a response without a body or a rendered wrapper.');
    }
    $body_scripts = $crawler->filter('body script');

    // Now create the new body to attach all the things to it.
    $new_body = $dom->createElement('body');
    // Clone the node attributes.
    foreach ($body->attributes as $attr_name => $attr_value) {
      $new_body->setAttribute($attr_name, $attr_value->value);
    }
    // Add into the new body, everything that we found inside the wrapper.
    foreach ($wrapper_contents as $node) {
      $new_body->appendChild($node);
    }
    // We also need any script that is found in the body, since there is no way
    // to ensure the script isn't necessary for our rendered template.
    foreach ($body_scripts as $body_script) {
      $new_body->appendChild($body_script);
    }

    // Make the new body take the place of the old.
    $dom->getElementsByTagName('html')
      ->item(0)
      ?->replaceChild($new_body, $body);

    // Set the new HTML in the response.
    $response->setContent($dom->saveHTML());
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['alter', -10];
    return $events;
  }

}
