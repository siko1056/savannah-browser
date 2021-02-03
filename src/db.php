<?php

require_once("config.php");

/**
 * Manage database access.
 */
class db
{
  private $pdo;  /// database connection

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
    if ($this->itemExists($item['ItemID'], $item['TrackerID'])) {

    } else {
      $id = $this->insertIntoItems($data);
      foreach ($discussion as $comment) {
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
                      '. $items_cols. ',
                      LastUpdated  TIMESTAMP)',
                   'CREATE TABLE IF NOT EXISTS Discussions (
                      ID           INTEGER PRIMARY KEY AUTOINCREMENT,
                      ItemID       INTEGER,
                      '. $discussions_cols. ',
                      FOREIGN KEY (ItemID)
                        REFERENCES Items(ID)
                          ON UPDATE CASCADE
                          ON DELETE CASCADE
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
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 1);
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

  private function insertIntoDiscussions($itemID, $data)
  {
    $columns = array_column(DISCUSSION_DATA, 1);
    $command = 'INSERT INTO Discussions
                          ( ItemID, ' . implode(",",  $columns) . ')
                    VALUES(:itemID,:' . implode(",:", $columns) . ')';
    $stmt = $this->connectDB()->prepare($command);
    $cols[':itemID'] = $itemID;
    foreach ($columns as $c) {
      $cols[':' . $c] = $item[$c];
    }
    $stmt->execute($cols);
  }

  private function itemExists(int $itemID, int $trackerID)
  {
    $command = 'SELECT EXISTS(SELECT 1 FROM Items WHERE ItemID=:ItemID
                                                    AND TrackerID=:TrackerID)';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([
      ':ItemID'    => $itemID,
      ':TrackerID' => $trackerID
      ]);
    $bool = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($bool[array_key_first($bool)] === '1');
  }

  /*
  private function getItemID(int $itemID, int $trackerID)
  {
    $command = 'SELECT ID FROM Items WHERE ItemID=:ItemID
                                       AND TrackerID=:TrackerID)';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([
      ':ItemID'    => $itemID,
      ':TrackerID' => $trackerID
      ]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
  }
  */
}

?>
