<?php

require_once("config.php");

// Getting data from Savannah.
class crawler
{
  public function getIDsFrom($tracker, $lastID)
  {
    // Get all group+tracker IDs from Savannah.
    $ids = array();
    $offset     = 0;
    $current_id = 1;
    $num_of_ids = (int) CONFIG::CHUNK_SIZE;
    $url = CONFIG::BASE_URL . "/$tracker/index.php"
                            . "?group=" . CONFIG::GROUP['id']
                            . "&status_id=0"
                            . "&chunksz=" . CONFIG::CHUNK_SIZE
                            . "&offset=";

    while (($current_id < $num_of_ids)
           && (empty($ids) || (end($ids) > $lastID))) {
      DEBUG_LOG("Crawl index '$tracker'.
                 From item $current_id to $num_of_ids until ID '$lastID'.
                 Last ID found: '" . end($ids) . "'");
      $doc = new DOMDocument;
      $doc->preserveWhiteSpace = false;
      $doc->loadHTMLFile(sprintf ("$url%d", $current_id));

      // Watching out for a string like "9027 matching items - Items 1 to 50",
      // where "9027" should be the total number of project bugs.
      $id_counts = $doc->getElementsByTagName('h2');
      if ($id_counts->length > 1) {
        preg_match_all('!\d+!', $id_counts->item(0)->nodeValue, $matches);
        $current_id = (int) $matches[0][2];
        $num_of_ids = (int) $matches[0][0];
      }

      // Find IDs on current page
      $xpath = new DOMXpath($doc);
      foreach ($xpath->query('//table[@class="box"]/tr/td[1]') as $id) {
        array_push($ids, (int) substr($id->nodeValue, 3));
      }

      $offset += CONFIG::CHUNK_SIZE;
    }

    return $ids;
  }

  public function crawl($tracker, int $id)
  {
    $item['ItemID']    = $id;
    $item['TrackerID'] = array_search($tracker, CONFIG::TRACKER_ID);

    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = false;
    $doc->loadHTMLFile(CONFIG::BASE_URL . "/$tracker/index.php?$id");

    // Extract title.
    $title = $doc->getElementsByTagName('h1');
    if ($title->length > 1) {
      $item['Title'] = explode(': ', $title[1]->nodeValue, 2)[1];
    } else {
      $item['Title'] = '???';
    }

    //FIXME: Check tracker string!

    // Match key value pairs in remaining metadata.
    $xpath = new DOMXpath($doc);
    $metadata = $xpath->query('//form/table[1]');
    if ($metadata->length > 0) {
      $metadata = explode("\n", $metadata[0]->nodeValue);
      foreach ($metadata as $idx=>$key) {
        $key = trim($key, " \u{a0}");  // remove space and fixed space
        if (array_key_exists($key, CONFIG::ITEM_DATA)) {
          $value = $metadata[$idx + 1];
          switch ($key) {
            case 'Submitted by:':
              $value = trim(htmlspecialchars($value));
              break;
            case 'Assigned to:':
              $value = trim(htmlspecialchars($value));
              break;
            case 'Submitted on:':          // TIMESTAMP
              $value = strtotime($value);
              break;
            case 'Open/Closed:':           // INTEGER
              $value = array_search(strtolower($value), CONFIG::ITEM_STATE);
              break;
          }
          $item[CONFIG::ITEM_DATA[$key][0]] = $value;
        }
      }
    }
    //FIXME: Add unassigned fields as empty strings.

    // Extract discussion for full-text search.
    $discussion = array();
    $table = $xpath->query('//table[@class="box" and position()=1]');
    if ($table->length > 0) {
      $maxDate = 0;
      foreach ($xpath->query('tr', $table[0]) as $comment) {
        $text   = $xpath->query('.//div'   , $comment);
        $date   = $xpath->query('./td[1]/a', $comment);
        $author = $xpath->query('./td[2]/a', $comment);
        if ($author->length > 0) {
          $author = htmlspecialchars($author[0]->nodeValue);
        } else {
          $author = 'Anonymous';
        }
        if ($date->length > 0) {
          $date = strtotime(explode(',', $date[0]->nodeValue)[0]);
        } else {
          $date = 0;
        }
        if ($text->length > 0) {
          $text = htmlspecialchars($this->DOMinnerHTML($text[0]));
        } else {
          $text = '???';
        }
        if ($date !== 0) {
          $maxDate = max($maxDate, $date);
          $new_item["Date"]   = $date;
          $new_item["Author"] = $author;
          $new_item["Text"]   = $text;
          array_push($discussion,$new_item);
        }
      }
      $item["LastComment"] = $maxDate;
    }
    return array($item, $discussion);
  }

  /**
   * Helper function to retrieve HTML from a DOM node.
   *
   * See https://stackoverflow.com/a/2087136/3778706
   */
  private function DOMinnerHTML(DOMNode $element)
  {
    $innerHTML = "";
    $children  = $element->childNodes;

    foreach ($children as $child) {
      $innerHTML .= $element->ownerDocument->saveHTML($child);
    }

    return $innerHTML;
  }
}

?>
