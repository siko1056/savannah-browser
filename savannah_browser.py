import csv
from savannah_proxy import savannah_proxy

if __name__ == '__main__':
  p = savannah_proxy()
  print(len(p.get_ids()))
