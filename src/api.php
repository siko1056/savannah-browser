<?php

require_once("config.php");
require_once("crawler.php");
require_once("db.php");

class api
{
  public function getItems($format, $filter=false)
  {
    $this->lookForNewItems();
    $db = new db();
    $items = $db->getItems($filter);
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

  private function lookForNewItems()
  {
    //FIXME: wait some time before next check.

    $crawler = new crawler();
    $db      = new db();

    foreach (CONFIG::TRACKER_ID as $tracker) {
      $lastID = $db->getLastItemIDFromTracker(array_search($tracker,
                                                           CONFIG::TRACKER_ID));
      $ids = $crawler->getIDsFrom($tracker, $lastID);
      // Traverse in reversed order in case of error.
      foreach (array_reverse($ids) as $id) {
        if ($id > $lastID) {
          DEBUG_LOG("Crawl new '$tracker' with ID '$id'.");
          list($item, $discussion) = $crawler->crawl($tracker, $id);
          $db->update($item, $discussion);
        }
      }
    }
  }

  /**
   * Translate IDs and TIMESTAMPS to human readable strings.
   *
   * @param item associative array with fields given in the "database column"
   *             of `CONST::ITEM_DATA`.
   */
  private function idsToString($item)
  {
    $item["TrackerID"]   = CONFIG::TRACKER_ID[$item["TrackerID"]];
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
