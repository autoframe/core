# Http git hook call for action for fetch pull checkout commit push

*Config with constants or constructor args*

```php

define('X_HUB_SIGNATURE','sha1 hook token'); //or use in AfrGitHook constructor
define('X_HUB_SIGNATURE_256','sha256 hook token'); //or use sha 256 


define('X_PATH_TO_GIT_REPO_DIR','path to current repo dir'); //or use in AfrGitExec constructor
```

---

```php
`AfrGitHook`

use Autoframe\Core\GitExecHook\AfrGitHook;
use Autoframe\Core\GitExecHook\AfrGitExec;

//Hook example
$oGhr = new AfrGitHook('HOOK_TOKEN', '', true);
$gitExec = new AfrGitExec(__DIR__ . '/origin');

//print_r($gitExec->setGitConfigDefault('autoframe','USER@gmail.com'));
//print_r($gitExec->gitCloneWithUserToken('https://github.com/autoframe/hx','USER','ghp_TOKEN'));


if ($oGhr->isPushOnMasterBranch()) {
    $aLog = $gitExec->allInOnePushCurrentThenSwitchToMasterPullAddCommitAndPush();
    echo '<pre>';
    print_r($aLog);
    echo '</pre>';
}

```

---

```php
`AfrGitExec`

use Autoframe\Core\GitExecHook\AfrGitExec;

$gitExec = new AfrGitExec('/PATH_TO_GIT_REPO_DIR');

echo '<pre>';
print_r(
    $gitExec->gitAddCommitAndPush(
        'Manual commit ' .  gmdate('Y-m-d H:i:s') . ' GMT',
        $gitExec->getCurrentBranchName()
    )
);
echo '</pre>';

```

---

# 🚀 git install on Cpanel / WHM 🤖

### 💻 Ubuntu

    sudo apt update
    sudo apt install git
    git --version

### 💻 CentOS 8

    sudo dnf update -y
    sudo dnf install git -y
    git --version

### 💻 CentOS 7
    sudo yum update
    sudo yum install git
    git --version

---

# ⚙️ Config

### ssh / gpg / armour / fingerprint
https://docs.github.com/en/github/authenticating-to-github/connecting-to-github-with-ssh/generating-a-new-ssh-key-and-adding-it-to-the-ssh-agent#generating-a-new-ssh-key

    ssh-keygen -o

### 📚 List current config

    git config --list

### 🛠️ Set config globally

    git config --global user.name "Your Name"
    git config --global user.email "you@example.com"

    git config --global core.logallrefupdates true
    git config --global core.autocrlf false
    git config --global core.symlinks false
    git config --global core.bare false
    git config --global core.ignorecase true
    git config --global core.eol lf

### 🔧 Set config to current project
just ignore ***--global***

    cd ~/some-dir/project-dir

# 🍀🌈🦄 Clone

    cd ~/some-dir/project-dir
    git clone git@github.com:autoframe/xxxx.git
    git clone 'https://github.com/autoframe/xxxx.git'

### ⛔ clone private repos
Create a new token for the repo: https://github.com/settings/tokens

    git clone https://user:TOKEN@github.com/autoframe/repo/
    git clone https://user:TOKEN@github.com/username/repo.git
    git clone https://oauth2:<YOUR-PERSONAL_ACCESS-TOKEN>@github.com/<your_user>/<your_repo>.git
    git clone https://<pat>@github.com/<your account or organization>/<repo>.git


### 📄 Afterward, you can do the following two steps to normalize all files:

    git rm --cached -r .  ⚠️ Remove every file from git's index.
    git reset --hard      ⚠️ Rewrite git's index to pick up all the new line endings.

---

# ❗⚠️ Reset commands

    git checkout .         #If you want to revert changes made to your working copy, do this:
    git reset              #If you want to revert changes made to the index (i.e., that you have added), do this. Warning this will reset all of your unpushed commits to master!:
    git clean -f           #If you want to remove untracked files (e.g., new files, generated files):
    git clean -fd          #Or untracked directories (e.g., new or automatically generated directories):

    git revert <commit 1> <commit 2>     #If you want to revert a change that you have committed, do this:

# 🌐 Read from upstream
    git pull        #Pull branch info
    git fetch       #Refresh branches

# 📥 Checkout
    git checkout master     #Checkout master branch
    git checkout -q master  #Quiet, suppress feedback messages.
    git checkout -f master  #When switching branches, proceed even if the index or the working tree differs from HEAD. This is used to throw away local changes.

### 🙈 checkout args
https://git-scm.com/docs/git-checkout

    -q, --quiet 
Quiet, suppress feedback messages.

    -f, --force 
When switching branches, proceed even if the index or the working tree differs from HEAD. This is used to throw away local changes.

    -b
**git checkout -b new-branch-name**

Create a new branch named <new_branch> and start it at <start_point>; see git-branch(1) for details.

    -m, --merge
When switching branches, if you have local modifications to one or more files that are different between the current branch and the branch to which you are switching, the command refuses to switch branches in order to preserve your modifications in context. However, with this option, a three-way merge between the current branch, your working tree contents, and the new branch is done, and you will be on the new branch.

    --ours, --theirs
When checking out paths from the index, do not fail upon unmerged entries; instead, unmerged entries are ignored.


# 📤 Push

    git diff
    git add .
    git commit -m "#uztpOegQ comment info"
    git commit -m "PROPT-6264 second commit"

    git push <remote> <branch>
    git push -u origin branch_name

### 🙈 push args
https://git-scm.com/docs/git-push

    -u, --set-upstream
For every branch that is up-to-date or successfully pushed, add upstream (tracking) reference, used by argument-less git-pull[1] and other commands.

    -q, --quiet
Suppress all output, including the listing of updated refs, unless an error occurs. Progress is not reported to the standard error stream.

    --all, --branches
Push all branches (i.e. refs under refs/heads/); cannot be used with other <refspec>.
