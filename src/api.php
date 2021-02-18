<?php

require_once("config.php");
require_once("crawler.php");
require_once("db.php");
require_once("formatter.php");

class api
{
  /**
   * Process an API request.
   *
   * @param requestParameterUnfiltered an array like created from `$_GET`.
   *
   * @returns a string containing the result of the web request.
   */
  public function processRequest($requestParameterUnfiltered)
  {
    // Sanitize user input.
    array_walk_recursive($requestParameterUnfiltered, function (&$value) {
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
      });
    $requestParameter = $requestParameterUnfiltered;

    // Determine API action.
    if (array_key_exists('action', $requestParameter)
        && (array_key_first($requestParameter) === 'action')) {
      $apiAction = $requestParameter['action'];
      unset($requestParameter['action']);
      switch ($apiAction) {
        case 'update':
          return $this->processUpdateRequest($requestParameter);
          break;
        case 'get':
          return $this->getItems('RichHTML');
          break;
        default:
          die("API error: 'action' value must be one of {update|get}.");
      }
    } else {
      die("API error: No 'action' as first parameter key specified.");
    }
  }


  /**
   * Process an API update request.
   *
   * @param requestParameter an array like created from `$_GET`.
   *
   * @returns a string containing the result of the web request.
   */
  private function processUpdateRequest($requestParameter)
  {
    $validKeys = ['tracker', 'ids'];
    foreach ($requestParameter as $key => $value) {
      if (array_search($key, $validKeys) === false) {
        die("API error: invalid parameter key.
                        Only {tracker|ids} are permitted for 'action=update'.");
      }
    }

    $tracker = null;
    if (array_key_exists('tracker', $requestParameter)) {
      if (array_search($requestParameter['tracker'], CONFIG::TRACKER) === false) {
        die("API error: invalid 'tracker' value.
             Only {" . implode('|', CONFIG::TRACKER). "} are permitted.");
      }
      $tracker = $requestParameter['tracker'];
    }

    $ids = array();
    if (array_key_exists('ids', $requestParameter)) {
      $ids = $requestParameter['ids'];
      if (preg_match('/[^0-9,]/', $ids)) {
        die("API error: invalid 'ids' value.
             Only characters 0-9 and comma as item separator ','
             are permitted.");
      }
      $ids = array_map('intval', explode(',', $ids));
    }

    return $this->lookForUpdates($tracker, $ids);
  }


  /**
   * Translate IDs and TIMESTAMPS to human readable strings.
   *
   * @param format one of 'HTML', 'HTMLCSS', 'JSON', 'CSV'.
   *
   * @param filter TODO: unused yet.
   *
   * @returns items formatted as string according to @p format.
   */
  private function getItems($format, $filter=false)
  {
    $items = DB::getInstance()->getItems($filter);
    $fmt = new formatter($items);
    switch ($format) {
      case 'RichHTML':
        return $fmt->asHTML(true);
        break;
      case 'HTML':
        return $fmt->asHTML();
        break;
      case 'JSON':
        return $fmt->asJSON();
        break;
      case 'CSV':
        return $fmt->asCSV();
        break;
      default:
        die("Invalid format, use 'HTML', 'HTMLCSS', 'JSON', 'CSV'");
        break;
    }
  }


  /**
   * Look for updates on Savannah and the mailing list archive and update the
   * database accordingly.
   *
   * @param tracker (optional) specify a tracker to narrow the search
   *
   * @param ids (optional) array of item IDs to narrow the search
   *
   * @returns nothing `null`.
   */
  private function lookForUpdates($tracker = null, $ids = array())
  {
    // If no tracker is given, recursive call over all trackers.
    if ($tracker === null) {
      foreach (CONFIG::TRACKER as $tracker) {
        $this->lookForUpdates($tracker, $ids);
      }
      return;
    }
    $trackerID = array_search($tracker, CONFIG::TRACKER);
    if ($trackerID === false) {
      DEBUG_LOG("Invalid tracker '$tracker'.");
      return;
    }
    $ids = (is_array($ids)) ? $ids : [$ids];  // ensure array

    $db = DB::getInstance();
    $crawler = new crawler();

    // If no IDs are specified, look for new or updated items.
    if (count($ids) == 0) {
      // Look for new items.
      $nextLookup = $db->getTimer("crawlNewItems_$tracker")
                  + CONFIG::DELAY["crawlNewItems"] - time();
      if ($nextLookup <= 0) {
        $db->setTimer("crawlNewItems_$tracker", time());
        $lastID = $db->getMaxItemIDFromTracker($trackerID);
        $ids = array_merge($ids, $crawler->crawlNewItems($tracker, $lastID));
      } else {
        DEBUG_LOG("'crawlNewItems_$tracker' delayed for $nextLookup seconds.");
      }

      // Look for update items, only if not much new is to be added.
      if (count($ids) < CONFIG::MAX_CRAWL_ITEMS) {
        $nextLookup = $db->getTimer("crawlUpdatedItems_$tracker")
                    + CONFIG::DELAY["crawlUpdatedItems"] - time();
        if ($nextLookup <= 0) {
          $db->setTimer("crawlUpdatedItems_$tracker", time());
          $lastComment = $db->getMaxLastCommentFromTracker($trackerID);
          $ids = array_merge($ids, $crawler->crawlUpdatedItems($tracker,
                                                              $lastComment));
        } else {
          DEBUG_LOG("'crawlUpdatedItems_$tracker'
                    delayed for $nextLookup seconds.");
        }
      } else {
        DEBUG_LOG("'crawlUpdatedItems_$tracker' skipped.");
      }
    } else {
      $nextLookup = $db->getTimer("crawlItem")
                  + CONFIG::DELAY["crawlItem"] - time();
      if ($nextLookup <= 0) {
        $db->setTimer("crawlItem", time());
      } else {
        DEBUG_LOG("'crawlItem' delayed for $nextLookup seconds.");
        return;
      }
      if (count($ids) > CONFIG::MAX_CRAWL_ITEMS) {
        DEBUG_LOG("'crawlItem' not more than "
                  . CONFIG::MAX_CRAWL_ITEMS . " item updates permitted.");
        return;
      }
    }

    $ids = array_unique($ids);
    sort($ids);  // oldest first
    foreach ($ids as $id) {
      DEBUG_LOG("--> Update item ID '$id' from '$tracker'.");
      list($item, $discussion) = $crawler->crawlItem($tracker, $id);
      if (isset($item) && isset($discussion)) {
        $db->update($item, $discussion);
      }
    }
  }
}

?>
