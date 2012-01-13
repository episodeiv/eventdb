#!/bin/python

import signal
import time

class t:
    def __init__(self):
        signal.signal(signal.SIGUSR1,self.sighandler)
        while True:
            time.sleep(100)

    def sighandler(self,test,test2):
        print("yay")


t()
