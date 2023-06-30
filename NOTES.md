# Notes

Just some notes for myself.

## Branches
* Catalyst - Main release branch of WisTex Catalyst
* WisTex - Development branch for WisTex
* Dev - Development branch of upstream (Streams repository) - NOT the dev branch of WisTex Catalyst
* Release - Release branch of upstream (Streams repository) - NOT the release of WisTex Catalyst

## Upstream Git

This repository pulls from the upstream repository at https://codeberg.org/streams/streams to keep it in sync with Streams.

To do this, the local computer has to pull from upstream, and then update the origin (Github) with the updates from the upstream.

    cd PROJECT_NAME
    $ git remote add upstream https://codeberg.org/streams/streams.git
    $ git fetch upstream
    
    # then: (like "git pull" which is fetch + merge)
    $ git merge upstream/release origin/release
    $ git merge upstream/dev origin/dev
    
    # or, better, replay your local work on top of the fetched branch
    # like a "git pull --rebase"
    $ git rebase upstream/release
    
    # Since we will never directly modify dev and release branches git merge will work fine. 
    # We will create a pull request if we want to merge the changes into the project.
    
    # Then push the changes to GitHub
    $ git push origin

https://stackoverflow.com/questions/3903817/pull-new-updates-from-original-github-repository-into-forked-github-repository/3903835#3903835

And then we create a pull request to merge it into the catalyst branch, which is our release branch.