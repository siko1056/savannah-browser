<?php

require_once("config.php");

// Getting data from Savannah.
class crawler
{
  public function readAllIDs()
  {
    // Get all group+tracker IDs from Savannah.
    $ids = array();
    $offset     = 0;
    $current_id = 0;
    $num_of_ids = 1;
    $url = sprintf ('%s/%s/index.php?group=%s&status_id=0&chunksz=%d&offset=',
                    CONFIG::URL, CONFIG::TRACKER, CONFIG::GROUP, CONFIG::CHUNK_SIZE);

    while ($current_id < $num_of_ids) {
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
      //FIXME: debug.
      $num_of_ids = 0;
    }

    return $ids;
  }

  public function crawl(int $id)
  {
    $data['ID:'] = $id;
    $url = sprintf ("%s/%s/index.php?$id", CONFIG::URL, CONFIG::TRACKER);
    $doc = new DOMDocument;
    $doc->preserveWhiteSpace = false;
    $doc->loadHTMLFile($url);

    // title
    $title = $doc->getElementsByTagName('h1');
    if ($title->length > 1) {
      $title = explode(': ', $title[1]->nodeValue, 2)[1];
    } else {
      $title = '???';
    }
    $data['Title:'] = $title;

    // Match key value pairs in remaining metadata.
    $xpath = new DOMXpath($doc);
    $metadata = $xpath->query('//form/table[1]');
    if ($metadata->length > 0) {
      $keys = array_column(CONFIG::BUGS_DATA, 0);
      $metadata = explode("\n", $metadata[0]->nodeValue);
      foreach ($metadata as $idx=>$key) {
        $key = trim($key, " \u{a0}");  // remove space and fixed space
        if (in_array($key, $keys)) {
          $value = $metadata[$idx + 1];
          switch ($key) {
            case 'Submitted by:':
              $value = trim(htmlspecialchars($value));
              break;
            case 'Submitted on:':
              $value = strtotime($value);  // TIMESTAMP
              break;
            case 'Open/Closed:':
              $value = ($value == "Open");  // BOOLEAN
              break;
          }
          $data[$key] = $value;
        }
      }
    }

    // Extract discussion for full-text search.
    $table = $xpath->query('//table[@class="box" and position()=1]');
    if ($table->length > 0) {
      $discussion = array();
      foreach ($xpath->query('tr', $table[0]) as $comment) {
        $text   = $xpath->query('.//div', $comment);
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
        if (($text !== '???') || ($date !== 0)) {
          $new_item["date"]   = $date;
          $new_item["author"] = $author;
          $new_item["text"]   = $text;
          array_push($discussion,$new_item);
        }
      }
      $data['discussion'] = $discussion;
    }
    return $data;
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
