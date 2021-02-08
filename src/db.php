<?php

require_once("config.php");

/**
 * Manage database access.
 */
class db
{
  private $pdo;  /// database connection

  public function getItems($filter)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $command = 'SELECT ' . implode(",",  $columns) . '
                FROM Items
                ORDER BY
                  TrackerID ASC,
                  ItemID    DESC';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute();
    $data = array();
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
      array_push($data, $item);
    }
    return $data;
  }

  public function getLastItemIDFromTracker(int $trackerID)
  {
    $command = 'SELECT MAX(ItemID) AS ItemID
                                   FROM  Items
                                   WHERE TrackerID=:TrackerID';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([':TrackerID' => $trackerID]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($id === false) ? -1 : (int) $id["ItemID"];
  }

  public function getLastCrawlingTime()
  {
    $command = 'SELECT LastCrawling FROM Meta WHERE ID = 1';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute();
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    return $id ? $id["LastCrawling"] : 0;
  }

  public function setLastCrawlingNow()
  {
    if ($this->getLastCrawlingTime() === 0) {
      $command = 'INSERT INTO Meta ( LastCrawling) VALUES (:LastCrawling)';
    } else {
      $command = 'UPDATE Meta
                  SET    LastCrawling = :LastCrawling
                  WHERE  ID = 1';
    }
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([':LastCrawling' => time()]);
  }

  /**
   * Update an item with discussion.
   *
   * @param item associative array with fields given in the "database column"
   *             of `CONST::ITEM_DATA`.
   * @param discussion associative array with fields given in the "database
   *                   column" of `CONST::DISCUSSION_DATA`.
   */
  public function update($item, $discussion)
  {
    $id = $this->getInteralItemID($item['ItemID'], $item['TrackerID']);
    if ($id > 0) {
      $this->updateItems($id, $item);
    } else {
      $id = $this->insertIntoItems($item);
    }
    foreach ($discussion as $comment) {
      $cid = $this->getCommentID($id, $comment['Date']);
      if ($cid === -1) {
        $this->insertIntoDiscussions($id, $comment);
      }
    }
  }

  /**
   * Ensure proper connection to database file.
   */
  private function connectDB()
  {
    if ($this->pdo == null) {
      try {
        $this->pdo = new PDO('sqlite:' . CONFIG::DB_FILE);
      } catch (PDOException $e) {
        exit("Cannot open database file (write protected?).");
      }
      $items_cols = '';
      foreach (array_values(CONFIG::ITEM_DATA) as $col) {
        $items_cols .= $col[0] . " " . $col[1] . ",";
      }
      $discussions_cols = '';
      foreach (CONFIG::DISCUSSION_DATA as $col) {
        $discussions_cols .= $col[0] . " " . $col[1] . ",";
      }
      $commands = ['CREATE TABLE IF NOT EXISTS Items (
                      ID           INTEGER PRIMARY KEY AUTOINCREMENT,
                      '. $items_cols. '
                      LastUpdated  TIMESTAMP NOT NULL)',
                   'CREATE TABLE IF NOT EXISTS Discussions (
                      ID           INTEGER PRIMARY KEY AUTOINCREMENT,
                      ItemID       INTEGER,
                      '. $discussions_cols. '
                      FOREIGN KEY (ItemID)
                        REFERENCES Items(ID)
                          ON UPDATE CASCADE
                          ON DELETE CASCADE
                    )',
                   'CREATE TABLE IF NOT EXISTS Meta (
                      ID           INTEGER PRIMARY KEY AUTOINCREMENT,
                      LastCrawling TIMESTAMP NOT NULL
                    )'];
      try {
        foreach ($commands as $command) {
          $this->pdo->exec($command);
        }
      } catch (PDOException $e) {
        exit("Database tables could not be created.");
      }
    }
    return $this->pdo;
  }

  private function insertIntoItems($item)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $command = 'INSERT INTO Items
                          ( ' . implode(",",  $columns) . ',LastUpdated)
                    VALUES(:' . implode(",:", $columns) . ',:now)';
    $db = $this->connectDB();
    $stmt = $db->prepare($command);
    $cols[':now'] = time();
    foreach ($columns as $c) {
      $cols[':' . $c] = $item[$c];
    }
    $stmt->execute($cols);
    return $db->lastInsertId();
  }

  private function insertIntoDiscussions($itemID, $comment)
  {
    $columns = array_column(CONFIG::DISCUSSION_DATA, 0);
    $command = 'INSERT INTO Discussions
                          ( ItemID, ' . implode(",",  $columns) . ')
                    VALUES(:itemID,:' . implode(",:", $columns) . ')';
    $stmt = $this->connectDB()->prepare($command);
    $cols[':itemID'] = $itemID;
    foreach ($columns as $c) {
      $cols[':' . $c] = $comment[$c];
    }
    $stmt->execute($cols);
  }

  private function updateItems(int $id, $item)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $command = 'UPDATE Items SET ';
    foreach ($columns as $c) {
      $command .= "$c = :$c, ";
    }
    $command   .= 'LastUpdated = :now
                   WHERE ID=:ID';
    $stmt = $this->connectDB()->prepare($command);
    $cols[':ID'] = $id;
    $cols[':now'] = time();
    foreach ($columns as $c) {
      $cols[':' . $c] = $item[$c];
    }
    $stmt->execute($cols);
  }

  private function getCommentID(int $itemID, int $date)
  {
    $command = 'SELECT ID FROM Discussions WHERE ItemID=:ItemID
                                             AND Date=:Date';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([
      ':ItemID' => $itemID,
      ':Date'   => $date
      ]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($id === false) ? -1 : (int) $id["ID"];
  }

  private function getInteralItemID(int $itemID, int $trackerID)
  {
    $command = 'SELECT ID FROM Items WHERE ItemID=:ItemID
                                       AND TrackerID=:TrackerID';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([
      ':ItemID'    => $itemID,
      ':TrackerID' => $trackerID
      ]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($id === false) ? -1 : (int) $id["ID"];
  }

}

?>
