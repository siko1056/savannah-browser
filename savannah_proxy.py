import csv
import os.path
import re
import requests
import requests_cache
import urllib.request
import warnings
from bs4 import BeautifulSoup
from pathlib import Path

class savannah_proxy:
  url = 'https://savannah.gnu.org'

  def __init__(self, tracker='bugs', group='octave', chunk_size=150, offset=0,
               cache_dir='cache'):
    self.tracker = tracker
    self.group = group
    self.chunk_size = chunk_size
    self.offset = offset
    self.cache_dir = cache_dir
    self.ids = []

    if not os.path.exists(self.cache_dir):
      os.makedirs(self.cache_dir)

    requests_cache.install_cache(os.path.join(self.cache_dir, 'savannah_cache'),
                                 backend='sqlite',
                                 expire_after=None)

  def get_id_cache_file_name(self):
    return Path(self.cache_dir +
                '/savannah_{self.group}_{self.tracker}.csv'.format(self=self))

  def get_ids(self):
    # Get all group+tracker IDs from Savannah.
    current_id = 0
    num_of_ids = 1
    while current_id < num_of_ids:
      response = requests.get('{self.url}/{self.tracker}/index.php'
        '?group={self.group}&status_id=0&chunksz={self.chunk_size}'
        '&offset={self.offset}'.format(self=self))
      soup = BeautifulSoup(response.text, "html.parser")

      # Watching out for a string like "9027 matching items - Items 1 to 50",
      # where "9027" should be the total number of project bugs.
      num_of_ids = soup.h2.get_text()
      num_of_ids = re.search(r'(\d+) matching items - Items \d+ to (\d+)', num_of_ids)
      current_id = int(num_of_ids[2])
      num_of_ids = int(num_of_ids[1])

      # Find IDs on current page
      for id in soup.find("table", class_="box").find_all("tr"):
        if id.td is not None:
          self.ids.append(id.td.a.get('href')[1:]) # Ignore leading "?"

      self.offset += self.chunk_size
      print("Read IDs {}/{}".format(self.offset, num_of_ids))

    return self.ids

  def get_item(self, id):
    """Return an item, a.k.a list of strings

    An item has the order of fields:

      "id", "title", "submitted_by", "submitted_on", "category", "severity",
      "priority", "item_group", "status", "assigned_to", "originator",
      "open_closed", "release", "operating_system"
    """
    item = [id]
    response = requests.get('{self.url}/{self.tracker}/index.php?{id}'
                            .format(self=self,id=id))
    soup = BeautifulSoup(response.text, "html.parser")

    # title
    item.append(soup.find_all('h1')[-1].get_text().split(": ", 1)[1])

    # Match key value pairs in remaining metadata.
    metadata = soup.find_all('form')[-1].table.get_text().split("\n")
    keys = ['Submitted by:\xa0', 'Submitted on:\xa0', 'Category:\xa0',
            'Severity:\xa0', 'Priority:\xa0', 'Item Group:\xa0', 'Status:\xa0',
            'Assigned to:\xa0', 'Originator Name:\xa0', 'Open/Closed:\xa0',
            'Release:\xa0', 'Operating System:\xa0']
    for i in range(len(keys)):
      item.append(metadata[metadata.index(keys[i]) + 1])
      if item[-1] in keys:
         item[-1] = ""

    return item

  def save_items_to_cache_file(self):
    with open(self.get_id_cache_file_name(), 'w', newline='') as f:
      cw = csv.writer(f)
      header = ["id", "title", "submitted_by", "submitted_on", "category",
                "severity", "priority", "item_group", "status", "assigned_to",
                "originator", "open_closed", "release", "operating_system"]
      cw.writerow(header)
      id_len = len(p.get_ids())
      for i, id in enumerate(p.get_ids()):
        print("Write ID {} ({}/{})".format(id, i, id_len))
        cw.writerow(p.get_item(id))


if __name__ == '__main__':
  p = savannah_proxy()
  p.save_items_to_cache_file()
