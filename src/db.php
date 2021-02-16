<?php

require_once("config.php");

/**
 * Manage database access.
 */
class db
{
  private $pdo;  /// database connection

  private static $instance = null;  /// Singleton pattern.

  /**
   * Constructor.
   */
  private final function  __construct()
  {
    if ($this->pdo == null) {
      // Open database.
      try {
        $this->pdo = new PDO('sqlite:' . CONFIG::DB_FILE);
      } catch (PDOException $e) {
        exit("Cannot open database file (write protected?). "
             . "Exception:\n\t". $e->getMessage());
      }

      // Check table structure.
      $items_cols = '';
      foreach (array_values(CONFIG::ITEM_DATA) as $col) {
        $items_cols .= $col[0] . " " . $col[1] . ",";
      }
      $discussions_cols = '';
      foreach (CONFIG::DISCUSSION_DATA as $col) {
        $discussions_cols .= $col[0] . " " . $col[1] . ",";
      }
      $commands = ['CREATE TABLE IF NOT EXISTS Items (
                      ID      INTEGER PRIMARY KEY AUTOINCREMENT,
                      '. $items_cols. '
                      LastUpdated  TIMESTAMP NOT NULL)',
                   'CREATE TABLE IF NOT EXISTS Discussions (
                      ID      INTEGER PRIMARY KEY AUTOINCREMENT,
                      ItemID  INTEGER,
                      '. $discussions_cols. '
                      FOREIGN KEY (ItemID)
                        REFERENCES Items(ID)
                          ON UPDATE CASCADE
                          ON DELETE CASCADE
                    )',
                   'CREATE TABLE IF NOT EXISTS Timer (
                      ID      INTEGER PRIMARY KEY AUTOINCREMENT,
                      Time    TIMESTAMP NOT NULL
                    )'];
      try {
        foreach ($commands as $command) {
          $this->pdo->exec($command);
        }
      } catch (PDOException $e) {
        exit("Database tables could not be created. "
             . "Exception:\n\t". $e->getMessage());
      }

      // Check timers are available.
      $lastTimerName = CONFIG::TIMER[count(CONFIG::TIMER) - 1];
      while ($this->getTimer($lastTimerName) === false) {
        $this->pdo->exec('INSERT INTO Timer (Time) VALUES (0)');
      }
    }
  }


  /**
   * Get database instance.
   *
   * @returns database instance.
   */
  public function getInstance()
  {
    if (!isset(self::$instance)) {
      self::$instance = new db();
    }
    return self::$instance;
  }


  /**
   * Retrieve a filtered list of items.
   *
   * @param filter FIXME: unused yet.
   *
   * @returns an array of items.
   */
  public function getItems($filter)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $command = 'SELECT ' . implode(",",  $columns) . '
                FROM Items
                ORDER BY
                  TrackerID ASC,
                  ItemID    DESC';
    $stmt = $this->pdo->prepare($command);
    $stmt->execute();
    $data = array();
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
      array_push($data, $item);
    }
    return $data;
  }


  /**
   * Get last item ID from a tracker.
   *
   * @param trackerID see index value of `CONST::TRACKER`.
   *
   * @returns item ID as integer or `false` on error.
   */
  public function getLastItemIDFromTracker(int $trackerID)
  {
    $command = 'SELECT MAX(ItemID) AS ItemID
                                   FROM  Items
                                   WHERE TrackerID=:TrackerID';
    $stmt = $this->pdo->prepare($command);
    $stmt->execute([':TrackerID' => $trackerID]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($id === false) ? false : (int) $id["ItemID"];
  }


  /**
   * Get timer value.
   *
   * @param timerName see `CONST::TIMER`.
   *
   * @returns timestamp as integer or `false` on error.
   */
  public function getTimer($timerName)
  {
    $id = array_search($timerName, CONFIG::TIMER);
    if ($id === false) {
      return false;
    }
    $command = 'SELECT Time FROM Timer WHERE ID = :ID';
    $stmt = $this->pdo->prepare($command);
    $stmt->execute([':ID' => $id + 1]);  // Index shift for database!
    $timestamp = $stmt->fetch(PDO::FETCH_ASSOC);
    return $timestamp ? (int) $timestamp["Time"] : false;
  }


  /**
   * Set timer value.
   *
   * @param timerName see `CONST::TIMER`.
   * @param timestamp value to set, for example `time()`.
   *
   * @returns nothing `null` or `false` on error.
   */
  public function setTimer($timerName, int $timestamp)
  {
    $id = array_search($timerName, CONFIG::TIMER);
    if ($id === false) {
      return false;
    }
    $command = 'UPDATE Timer
                SET    Time = :Time
                WHERE  ID   = :ID';
    $stmt = $this->pdo->prepare($command);
    $stmt->execute([':Time' => $timestamp,
                    ':ID'   => $id + 1]);  // Index shift for database!
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


  private function insertIntoItems($item)
  {
    $columns = array_column(array_values(CONFIG::ITEM_DATA), 0);
    $command = 'INSERT INTO Items
                          ( ' . implode(",",  $columns) . ',LastUpdated)
                    VALUES(:' . implode(",:", $columns) . ',:now)';
    $db = $this->pdo;
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
    $stmt = $this->pdo->prepare($command);
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
    $stmt = $this->pdo->prepare($command);
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
    $stmt = $this->pdo->prepare($command);
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
    $stmt = $this->pdo->prepare($command);
    $stmt->execute([
      ':ItemID'    => $itemID,
      ':TrackerID' => $trackerID
      ]);
    $id = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($id === false) ? -1 : (int) $id["ID"];
  }

}

?>
