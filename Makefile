bin_path:=$(PWD)/bin
GCC=/bin/bash
clean_script_path=./clean.sh

run:
	mkdir -p $(bin_path)
	chmod u+x bin/*

install: $(bin_path)/*
	for file in $^ ; do \
	    chmod u+x $${file}; \
		sudo ln -s $${file} /usr/local/bin ; \
	done

clean:
	$(GCC) $(clean_script_path)