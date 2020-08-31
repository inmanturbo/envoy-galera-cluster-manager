#!/bin/bash
for file in $(ls bin/)
    do
        sudo unlink /usr/local/bin/${file}
    done