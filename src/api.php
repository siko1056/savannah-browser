<?php

require_once("config.php");
require_once("crawler.php");
require_once("db.php");

class api
{
  /**
   * Translate IDs and TIMESTAMPS to human readable strings.
   *
   * @param format one of 'HTML', 'HTMLCSS', 'JSON', 'CSV'.
   *
   * @param filter TODO: unused yet.
   *
   * @returns items formatted as string according to @p format.
   */
  public function getItems($format, $filter=false)
  {
    $this->lookForUpdates();
    $items = DB::getInstance()->getItems($filter);
    switch ($format) {
      case 'RichHTML':
        return $this->itemsAsHTML($items, true);
        break;
      case 'HTML':
        return $this->itemsAsHTML($items);
        break;
      case 'JSON':
        return $this->itemsAsJSON($items);
        break;
      case 'CSV':
        return $this->itemsAsCSV($items);
        break;
      default:
        error("Invalid format, use 'HTML', 'HTMLCSS', 'JSON', 'CSV'");
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
    if (! isset($tracker)) {
      foreach (CONFIG::TRACKER as $tracker) {
        $this->lookForUpdates($tracker);
      }
      return;
    }
    $trackerID = array_search($tracker, CONFIG::TRACKER);
    $ids = (is_array($ids)) ? $ids : [$ids];  // ensure array

    $db = DB::getInstance();
    $crawler = new crawler();

    // If no IDs are specified, look for new or updated items.
    if (count($ids) == 0) {
      $nextLookup = $db->getTimer("crawlNewItems_$tracker")
                  + CONFIG::DELAY["crawlNewItems"] - time();
      if ($nextLookup <= 0) {
        $db->setTimer("crawlNewItems_$tracker", time());
        $lastID = $db->getLastItemIDFromTracker($trackerID);
        $ids += $crawler->crawlNewItems($tracker, $lastID);
      } else {
        DEBUG_LOG("'crawlNewItems_$tracker' delayed for $nextLookup seconds.");
      }
    }

    sort(array_unique($ids));  // oldest first
    foreach ($ids as $id) {
      DEBUG_LOG("--> Update item ID '$id' from '$tracker'.");
      list($item, $discussion) = $crawler->crawlItem($tracker, $id);
      if (isset($item) && isset($discussion)) {
        $db->update($item, $discussion);
      }
    }
  }


  /**
   * Translate IDs and TIMESTAMPS to human readable strings.
   *
   * @param item associative array with fields given in the "database column"
   *             of `CONST::ITEM_DATA`.
   *
   * @returns item with all IDs and TIMESTAMPS as human readable strings.
   */
  private function idsToString($item)
  {
    $item["TrackerID"]   = CONFIG::TRACKER[$item["TrackerID"]];
    $item["OpenClosed"]  = CONFIG::ITEM_STATE[$item["OpenClosed"]];
    $item["SubmittedOn"] = date(DATE_RFC2822, $item["SubmittedOn"]);
    $item["LastComment"] = date(DATE_RFC2822, $item["LastComment"]);
    return $item;
  }


  /**
   * Retrieve Savannah css class from item's priority.
   *
   * @param item associative array with fields given in the "database column"
   *             of `CONST::ITEM_DATA`.
   *
   * @returns a string with the css class attribute.
   */
  private function cssPriority($item)
  {
    // Translate something like "5 - Normal" to "e", etc.
    $str = chr(ord('a') + ((int) $item["Priority"][0]) - 1);
    $str .= ($item["OpenClosed"] == "closed") ? 'closed' : '';
    return " class=\"prior$str\"";
  }


  /**
   * Add HTML URLs to Savannah where possible.
   *
   * Must be called after `idsToString()`.
   *
   * @param item associative array with fields given in the "database column"
   *             of `CONST::ITEM_DATA`.
   *
   * @returns item inserted URLs.
   */
  private function addURLs($item)
  {
    $id  = $item["ItemID"];
    $url = CONFIG::BASE_URL . '/' . $item["TrackerID"] . "/index.php?$id";

    $item["ItemID"] = "<a href=\"$url\">$id</a>";
    $item["Title"]  = "<a href=\"$url\">" . $item["Title"] . "</a>";
    return $item;
  }


  /**
   * Get HTML representation of a list of items (without discussion).
   *
   * @param items list of associative arrays with fields given in the
   *              "database column" of `CONST::ITEM_DATA`.
   *
   * @param color (default = false) add Savannah compatible css classes.
   *
   * @returns item as HTML string.
   */
  private function itemsAsHTML($items, $color = false)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $str = '<tr><th>' . implode('</th><th>', $columns) . '</th></tr>';
    foreach ($items as $item) {
      $item = $this->idsToString($item);
      $css = ($color) ? $this->cssPriority($item) : '';
      if ($color) {
        $item = $this->addURLs($item);
      }
      $item_str = '';
      foreach ($item as $col) {
        $item_str .= "<td$css>$col</td>";
      }
      $str .= "<tr>$item_str</tr>";
    }
    return "<table>$str</table>";
  }

  /**
   * Get JSON representation of a list of items (without discussion).
   *
   * @param items list of associative arrays with fields given in the
   *              "database column" of `CONST::ITEM_DATA`.
   *
   * @returns item as JSON string.
   */
  private function itemsAsJSON($items)
  {
    foreach ($items as $idx=>$item) {
      $items[$idx] = $this->idsToString($item);
    }
    return json_encode($items);
  }

  /**
   * Get CSV representation of a list of items (without discussion).
   *
   * @param items list of associative arrays with fields given in the
   *              "database column" of `CONST::ITEM_DATA`.
   *
   * @returns item as CSV string.
   */
  private function itemsAsCSV($items)
  {
    $str = '';
    foreach ($items as $item) {
      $str .= '"' . implode('","', $this->idsToString($item)) . '"' . "\n";
    }
    return $str;
  }
}

?>
