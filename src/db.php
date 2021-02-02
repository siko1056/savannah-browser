<?php

require_once("config.php");

// Manage database access.
class db
{
  private $pdo;  // Holds the opened database connection.

  public function update($data)
  {
    if ($this->existsBug($data['ID:'])) {
    } else {
      $columns = array_column(CONFIG::BUGS_DATA, 1);
      $command = 'INSERT INTO Bugs( ' . implode(",",  $columns) . ') '
                  .        'VALUES(:' . implode(",:", $columns) . ')';
      $stmt = $this->connectDB()->prepare($command);
      $columns = array();
      foreach (CONFIG::BUGS_DATA as $col) {
        $columns[':' . $col[1]] = $data[$col[0]];
      }
      $stmt->execute($columns);
    }

    //FIXME:multiple values!
    $command = 'INSERT INTO BugsDiscussion(BugID,date,author,text) '
                . 'VALUES(:bug,:date,:author,:text)';
    $stmt = $db->prepare($command);
    $stmt->execute([
      ':bug'    => $data['ID:'],
      ':date'   => $date,
      ':author' => $author,
      ':text'   => $text
    ]);
  }

  private function connectDB()
  {
    if ($this->pdo == null) {
      try {
        $this->pdo = new PDO('sqlite:' . CONFIG::DB_FILE);
      } catch (PDOException $e) {
        exit("Cannot connect to database.");
      }
      $columns = '';
      foreach (CONFIG::BUGS_DATA as $col) {
        $columns .= $col[1] . " " . $col[2] . ",";
      }
      $columns = substr($columns, 0, -1);
      $commands = ['CREATE TABLE IF NOT EXISTS Bugs (' . $columns . ')',
                   'CREATE TABLE IF NOT EXISTS BugsDiscussion (
                      ID     INTEGER PRIMARY KEY AUTOINCREMENT,
                      BugID  INTEGER,
                      date   TIMESTAMP,
                      author TEXT,
                      text   LONGTEXT,
                      FOREIGN KEY (BugID)
                        REFERENCES Bugs(ID)
                          ON UPDATE CASCADE
                          ON DELETE CASCADE
                    )'];
      foreach ($commands as $command) {
        try {
          $this->pdo->exec($command);
        } catch (PDOException $e) {
          exit("Database tables could not be created.");
        }
      }
    }
    return $this->pdo;
  }

  private function existsBug($id)
  {
    $command = 'SELECT EXISTS(SELECT 1 FROM Bugs WHERE ID=:ID)';
    $stmt = $this->connectDB()->prepare($command);
    $stmt->execute([':ID' => $id]);
    $bool = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($bool[array_key_first($bool)] === '1');
  }
}

?>
