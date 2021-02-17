<?php

require_once("config.php");

class formatter
{
  private $items;

  /**
   * Constructor.
   */
  public function  __construct($items)
  {
    $this->items = $items;
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
  public function asHTML($color = false)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $str = '<tr><th>' . implode('</th><th>', $columns) . '</th></tr>';
    foreach ($this->items as $item) {
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
  public function asJSON()
  {
    $items = $this->items;
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
  public function asCSV()
  {
    $str = '';
    foreach ($this->items as $item) {
      $str .= '"' . implode('","', $this->idsToString($item)) . '"' . "\n";
    }
    return $str;
  }
}
?>
