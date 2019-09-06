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
    # Try to read IDs from cache first.
    if os.path.exists(self.get_id_cache_file_name()):
      print("Read IDs from '{}'.".format(self.get_id_cache_file_name()))
      return self.get_ids_from_cache_file()

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

    self.save_ids_to_cache_file()
    return self.ids

  def get_ids_from_cache_file(self):
    with open(self.get_id_cache_file_name(), newline='') as f:
      for row in csv.reader(f):
        self.ids.append(row[0])
      return self.ids

  def save_ids_to_cache_file(self):
    with open(self.get_id_cache_file_name(), 'w', newline='') as f:
      for id in self.ids:
        f.write(id + '\n')
