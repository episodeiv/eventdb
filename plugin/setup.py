import os
from setuptools import setup

# Utility function to read the README file.
# Used for the long_description.  It's nice, because now 1) we have a top level
# README file and 2) it's easier to type in the README file than to put a raw
# string in below ...
def read(fname):
    return open(os.path.join(os.path.dirname(__file__), fname)).read()

setup(
      name="EventDB_check_plugin",
      version="2.0.4",
      author="Jannis Mosshammer",
      author_email="jannis.mosshammer@netways.de",
      description=("Icinga/Nagios check plugin for the eventdb "),
      license="GPLv2",
      keywords="eventdb nagios icinga monitoring netways",
      url="http://www.netways.org/projects/eventdb",
      packages=['eventdb','eventdb.bin'],
      long_description=read('../doc/README'),
      entry_points={
        'console_scripts': ['check_eventdb = eventdb.bin.check_eventdb:main']
      },

      classifiers=[
        "Development Status :: 3 - Alpha",
        "Topic :: System :: Monitoring",
        "License :: OSI Approved :: GNU General Public License (GPL)"
      ]
)