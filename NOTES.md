# Notes

Just some notes for myself.

This repository pulls from the upstrean Streams repository at https://codeberg.org/streams/streams to keep it in sync with Streams.

To do this, the local computer has to pull from upstream, and then update the origin (Github) with the updates from the upstream.

    cd PROJECT_NAME
    $ git remote add upstream https://codeberg.org/streams/streams.git
    $ git fetch upstream
    
    # then: (like "git pull" which is fetch + merge)
    $ git merge upstream/master master
    
    # or, better, replay your local work on top of the fetched branch
    # like a "git pull --rebase"
    $ git rebase upstream/master

https://stackoverflow.com/questions/3903817/pull-new-updates-from-original-github-repository-into-forked-github-repository/3903835#3903835
