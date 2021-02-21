<?php

require_once("config.php");
require_once("crawler.php");
require_once("db.php");
require_once("formatter.php");

class api
{
  /**
   * Valid keys and data types for API action requests.
   */
  private $apiActions = null;

  /**
   * Constructor.
   */
  function __construct()
  {
    $this->apiActions = [
      'get'    => array_combine(
                    array_column(array_values(CONFIG::ITEM_DATA), 0),
                    array_column(array_values(CONFIG::ITEM_DATA), 1)
                    ),
      'update' => array_slice(array_combine(
                    array_column(array_values(CONFIG::ITEM_DATA), 0),
                    array_column(array_values(CONFIG::ITEM_DATA), 1)
                    ), 0, 2)
      ];
  }

  /**
   * Process an API request.
   *
   * @param requestParameterUnfiltered an array like created from `$_GET`.
   *
   * @returns a string containing the result of the web request.
   */
  public function processRequest($requestParameterUnfiltered)
  {
    $requestParameter = $this->validateRequest($requestParameterUnfiltered);
    if (is_string($requestParameter)) {
      die ("API error: $requestParameter");
    }

    switch ($requestParameter['Action']) {
      case 'update':
        $tracker = array_key_exists('TrackerID', $requestParameter)
                   ? $requestParameter['TrackerID']
                   : null;
        $ids = array_key_exists('ItemID', $requestParameter)
               ? $requestParameter['ItemID']
               : array();
        $success = $this->lookForUpdates($tracker, $ids);
        return ($success === true) ? "Update complete."
                                   : die ("API error: $success");
        break;
      case 'get':
        return $this->getItems('RichHTML');
        break;
      default:
        die("API error: 'action' value must be one of {update|get}.");
    }
  }


  /**
   * Validate request parameters.
   *
   * In PHP $_GET array keys should be unique and the rightmost key-value
   * pair is chosen.  All parameters are case insensitive.
   *
   * @param req an array like created from `$_GET`.
   *
   * @return a valid API request otherwise a string with an error message.
   */
  private function validateRequest($req)
  {
    // Sanitize user input.
    array_walk_recursive($req, function (&$value) {
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
      });

    // Validate API action.
    if (!array_key_exists('Action', $req)) {
      return "No parameter key 'Action' specified.";
    }
    $keys = array_keys($this->apiActions);
    if (!in_array($req['Action'], $keys)) {
      return "Parameter 'Action' value must be one of
              {" . implode('|', $keys) . "}.";
    }
    $validRequest['Action'] = $req['Action'];

    // Validate remaining parameters.
    $validKeys = array_keys($this->apiActions[$req['Action']]);
    foreach ($req as $key => $value) {
      // Check key to be valid.
      if ($key === 'Action') {
        continue;
      }
      if (!in_array($key, $validKeys)) {
        return "Unknown parameter key '$key'
                for 'Action=" . $req['Action'] . ".'
                Valid parameter keys are: {" . implode('|', $keys) . "}.";
      }

      // Separate values by ',' without empty elements.
      $value = array_filter(explode(',', $req[$key]));
      if (!is_array($value) || count($value) === 0) {
        return "Invalid or empty value for parameter keys '$key'.";
      }
      $validRequest[$key] = $value;
    }

    return $validRequest;
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
    if (($tracker === null) || is_array($tracker)) {
      if ($tracker === null) {
        $tracker = CONFIG::TRACKER;
      }
      foreach ($tracker as $singleTracker) {
        $success = $this->lookForUpdates($singleTracker, $ids);
        if ($success !== true) {  // Fail fast.
          return $success;
        }
      }
      return $success;
    }
    $trackerID = array_search($tracker, CONFIG::TRACKER);
    if ($trackerID === false) {
      return "Invalid TrackerID '$tracker'.  Stopping.";
    }
    if (!is_array($ids)) {
      return "Invalid ItemID '$ids'.  Stopping.";
    }

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
        return "'crawlNewItems_$tracker' delayed for $nextLookup seconds.";
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
          return "'crawlUpdatedItems_$tracker'
                 delayed for $nextLookup seconds.";
        }
      } else {
        return "'crawlUpdatedItems_$tracker' skipped.";
      }
    } else {
      $nextLookup = $db->getTimer("crawlItem")
                  + CONFIG::DELAY["crawlItem"] - time();
      if ($nextLookup <= 0) {
        $db->setTimer("crawlItem", time());
      } else {
        return "'crawlItem' delayed for $nextLookup seconds.";
      }
      if (count($ids) > CONFIG::MAX_CRAWL_ITEMS) {
        return "'crawlItem' not more than "
               . CONFIG::MAX_CRAWL_ITEMS . " item updates permitted.";
      }
    }

    $ids = array_unique($ids);
    sort($ids);  // oldest first
    foreach ($ids as $id) {
      $id = intval($id);
      if ($id === 0) {
        return "Invalid ItemID found.  Stopping.";
      }
      DEBUG_LOG("--> Update item ID '$id' from '$tracker'.");
      list($item, $discussion) = $crawler->crawlItem($tracker, $id);
      if (isset($item) && isset($discussion)) {
        $db->update($item, $discussion);
      }
    }

    // Success.
    return true;
  }
}

?>
