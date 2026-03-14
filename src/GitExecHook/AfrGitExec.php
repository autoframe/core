<?php

namespace Autoframe\Core\GitExecHook;

use Exception;


/*
/// Manual Push:
echo '<pre>';
$gitExec = new AfrGitExec('/PATH_TO_GIT_REPO_DIR');
print_r(
    $gitExec->gitAddCommitAndPush(
        'Manual commit ' .  gmdate('Y-m-d H:i:s') . ' GMT',
        $gitExec->getCurrentBranchName()
    )
);
echo '</pre>';
*/

class AfrGitExec
{
    public static array $defaultMasterBranchNames = ['main', 'master'];
    public static int $iSleepMsBetweenExecs = 35;
    protected string $gitRepoDir;
    protected string $gitExePath = 'git';
    protected string $gitOrigin = 'origin';
    protected bool $bOldGitPathVersion = false;
    protected float $fV = 2.0;

    /**
     * Create a new instance.
     * @param string $gitRepoDir
     * @param string $gitExePath
     * @param string $gitOrigin
     * @param bool $bUsePathNotCArg
     * @throws Exception
     */
    public function __construct(
        string $gitRepoDir,
        string $gitExePath = 'git',
        string $gitOrigin = 'origin',
        bool   $bUsePathNotCArg = false
    )
    {
        if (!$gitRepoDir) {
            $sEnvConstant = 'X_PATH_TO_GIT_REPO_DIR';
            $gitRepoDir = defined($sEnvConstant) ? constant($sEnvConstant) : ($_ENV[$sEnvConstant] ?? null);
        }

        $this->gitRepoDir($gitRepoDir);
        $this->gitExePath($gitExePath);
        $this->gitOrigin($gitOrigin);

        $aVersionInfo = $this->execCmd('--version', false);
        if (empty($aVersionInfo['gitExitSuccess'])) {
            throw new Exception('Git is not installed on current system or miss configured or PHP exec() is not permitted!');
        }

        if (
            !empty($aVersionInfo['execReturn']) &&
            strpos($aVersionInfo['execReturn'], 'git version') !== false
        ) {
            $vA = explode('.', trim(trim($aVersionInfo['execReturn']), 'git verson'));
            $version = $vA[0];
            unset($vA[0]);
            if (strlen($version) && (int)$version !== 0) {
                $this->fV = (int)$version + floatval('0.' . implode('', $vA));
            }
        }

        if ($this->fV < 2) {
            $bUsePathNotCArg = true;
        }
        $this->bOldGitPathVersion = $bUsePathNotCArg;
    }


    /**
     * Git repo dir.
     */
    public function gitRepoDir(string $gitRepoDir = null): string
    {
        if ($gitRepoDir !== null) {
            $gitRepoDir = trim($gitRepoDir, "\"\n\r\t\v\0");
            if (strpos($gitRepoDir, ' ') !== false) {
                $gitRepoDir = '"' . $gitRepoDir . '"';
            }
            $this->gitRepoDir = trim($gitRepoDir);
        }
        return $this->gitRepoDir;
    }

    /**
     * Git origin.
     */
    public function gitOrigin(string $gitOrigin = null): string
    {
        if ($gitOrigin !== null) {
            $this->gitOrigin = trim($gitOrigin);
        }
        return $this->gitOrigin;
    }

    /**
     * Git exe path.
     */
    public function gitExePath(string $gitExePath = null): string
    {
        if ($gitExePath !== null) {
            $this->gitExePath = trim($gitExePath);
        }
        return $this->gitExePath;
    }

    /**
     * Exec cmd.
     */
    public function execCmd(
        string $sGitArgs,
        bool   $withGitRepoDir = true,
        bool   $b21 = true
    ): array
    {
        $sCmd = $this->gitExePath();
        if ($withGitRepoDir) {
            if ($this->bOldGitPathVersion) {
                $sCmd = 'cd ' . $this->gitRepoDir . ' && ' . $sCmd;
            } else {
                $sCmd .= ' -C ' . $this->gitRepoDir;
            }
        }
        $sCmd .= ' ' . $sGitArgs . ($b21 ? ' 2>&1' : '');

        $execReturn = exec(
            $sCmd,
            $gitOutputLines,
            $gitExitCode
        );
        if (static::$iSleepMsBetweenExecs) {
            usleep(static::$iSleepMsBetweenExecs * 1000); //35 ms
        }
        return [
            'gitExitSuccess' => $gitExitCode == 0,
            'gitExitCode' => $gitExitCode,
            'gitOutputLines' => $gitOutputLines,
            'execReturn' => $execReturn,
            'call' => $sCmd,
        ];
    }

    /**
     * Set git config default.
     */
    public function setGitConfigDefault(
        string $sUsername,
        string $sEmail,
        bool   $bGlobal = true,
        string $logallrefupdates = 'true',
        string $autocrlf = 'false',
        string $symlinks = 'false',
        string $bare = 'false',
        string $ignorecase = 'true',
        string $eol = 'lf'
    ): array
    {
        $aReturn = [];
        $sAction = 'config ' . ($bGlobal ? '--global ' : '');
        foreach ([
                     'user.name "' . $sUsername . '"',
                     'user.email "' . $sEmail . '"',
                     'core.logallrefupdates ' . $logallrefupdates,
                     'core.autocrlf ' . $autocrlf,
                     'core.symlinks ' . $symlinks,
                     'core.bare ' . $bare,
                     'core.ignorecase ' . $ignorecase,
                     'core.eol ' . $eol,
                 ] as $sCmd) {
            $aReturn[] = $this->execCmd($sAction . $sCmd, !$bGlobal);
        }
        return $aReturn;
    }

    /**
     * Command: git clone https://user:TOKEN@github.com/autoframe/repo/
     * @param string $sRepoUrl
     * @param string $sUsername
     * @param string $sClassicToken
     * @param string $sMoreArgs
     * @return array
     */
    public function gitCloneWithUserToken(
        string $sRepoUrl,
        string $sUsername = '',
        string $sClassicToken = '', // https://github.com/settings/tokens
        string $sMoreArgs = ''
    ): array
    {
        if (strpos($sRepoUrl, '@') === false && $sUsername && $sClassicToken) {
            $aUrl = explode('//', $sRepoUrl);
            $aUrl[1] = urlencode($sUsername) . ':' . urlencode($sClassicToken) . '@' . $aUrl[1];
            $sRepoUrl = implode('//', $aUrl);
        }

        return $this->execCmd('clone ' . $sMoreArgs . $sRepoUrl . ' ' . $this->gitRepoDir, false);
    }


    /**
     * Git revert changes from working copy.
     */
    public function gitRevertChangesFromWorkingCopy(): array
    {
        return $this->execCmd('checkout .');
    }

    /**
     * Git reset changes to index and unpushed commits.
     */
    public function gitResetChangesToIndexAndUnpushedCommits(bool $hard = false): array
    {
        return $this->execCmd('reset' . ($hard ? ' --hard' : ''));
    }

    /**
     * Git reset hard cached indexes.
     */
    public function gitResetHardCachedIndexes(): array
    {
        return [
            $this->execCmd('rm --cached -r .'), //Remove every file from git's index.
            $this->gitResetChangesToIndexAndUnpushedCommits(true),   //Rewrite git's index to pick up all the new line endings.
        ];
    }

    /**
     * Git clean untracked files directories.
     */
    public function gitCleanUntrackedFilesDirectories(
        bool $files = true,
        bool $directories = true,
        bool $quiet = false
    ): array
    {
        $sFlags = $files ? 'f' : '';
        $sFlags .= $directories ? 'd' : '';
        $sFlags .= $quiet ? 'q' : '';
        if ($sFlags) {
            $sFlags = ' -' . $sFlags;
        }
        return $this->execCmd('clean' . $sFlags);
    }

    /**
     * Git revert commit12.
     */
    public function gitRevertCommit12(string $sCommit1, string $sCommit2): array
    {
        return $this->execCmd('revert ' . $sCommit1 . ' ' . $sCommit2);
    }

    /**
     * Git pull.
     */
    public function gitPull(string $remote = '', string $branch = ''): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        if (!$branch) {
            $branch = $this->getCurrentBranchName();
        }
        return $this->execCmd(trim("pull $remote $branch"));
    }

    /**
     * Git pull force.
     */
    public function gitPullForce(string $remote = ''): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return $this->execCmd('pull -f ' . $remote);
    }

    /**
     * Git fetch.
     */
    public function gitFetch(string $remote = '-all'): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return $this->execCmd('fetch ' . $remote);
    }

    /**
     * Git diff.
     */
    public function gitDiff(string $args = ''): array
    {
        return $this->execCmd(trim('diff ' . $args));
    }

    /**
     * Git status.
     */
    public function gitStatus(string $args = ''): array
    {
        return $this->execCmd(trim('status ' . $args));
    }

    /**
     * Git add commit and push.
     */
    public function gitAddCommitAndPush(string $sCommitMessage, string $sBranch, string $remote = ''): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        $aOut = [
            $this->gitFetch($remote),
            $this->gitPull($remote),
        ];
        $aOut = array_merge($aOut, $this->gitAddAndCommit($sCommitMessage));
        $aOut[] = $this->gitPush($sBranch, $remote);
        return $aOut;
    }

    /**
     * Git push.
     */
    public function gitPush(string $sBranch, string $remote = '', string $gitArgs = '-u'): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return $this->execCmd('push ' . ($gitArgs ? trim($gitArgs) . ' ' : '') . $remote . ' ' . $sBranch);
    }

    /**
     * Git add and commit.
     */
    public function gitAddAndCommit(string $sCommitMessage): array
    {
        return [
            $this->execCmd('add .'),
            $this->execCmd('commit -m "' . $sCommitMessage . '"'),
        ];
    }

    /**
     * Git hook fetch.
     */
    public function gitHookFetch(string $remote = ''): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return [
            $this->execCmd('--version'),
            $this->gitFetch($remote),
            $this->gitPull($remote)
        ];
    }

    /**
     * Git checkout new branch.
     */
    public function gitCheckoutNewBranch(string $sBranch): array
    {
        return $this->execCmd('checkout -b ' . $sBranch);
    }

    /**
     * Git checkout branch.
     */
    public function gitCheckoutBranch(string $sBranch): array
    {
        return $this->execCmd('checkout ' . $sBranch);
    }

    /**
     * Get current branch name.
     */
    public function getCurrentBranchName(): string
    {
        if ($this->fV < 2) {
            return $this->getBranchList()[0];
        }
        return trim($this->execCmd('branch --show-current')['execReturn']);

    }

    /**
     * Get branch list.
     */
    public function getBranchList(): array
    {
        $aBranches = [];
        $sSelected = '';

        $aLines = $this->execCmd('branch')['gitOutputLines'];
        if (!is_array($aLines)) {
            $aLines = [$aLines];
        }
        foreach ($aLines as $aLine) {
            $aLine = trim($aLine);
            if (!$sSelected && substr($aLine, 0, 1) === '*') {
                $sSelected = trim($aLine, '* ');
            } else {
                $aBranches[] = trim($aLine, '* ');
            }
        }
        if ($sSelected) {
            $aBranches = array_merge([$sSelected], $aBranches);
        }
        return $aBranches;
    }

    /**
     * Get master branch name.
     */
    public function getMasterBranchName(): ?string
    {
        if (count(self::$defaultMasterBranchNames) === 1) {
            return self::$defaultMasterBranchNames[0];
        }

        foreach ($this->getBranchList() as $sBranch) {
            if (in_array($sBranch, self::$defaultMasterBranchNames)) {
                return $sBranch;
            }
        }
        return null;
    }

    /**
     * Is on master branch.
     */
    public function isOnMasterBranch(): bool
    {
        return in_array($this->getCurrentBranchName(), self::$defaultMasterBranchNames);
    }

    /**
     * Checkout master branch.
     */
    public function checkoutMasterBranch(): array
    {
        return $this->gitCheckoutBranch($this->getMasterBranchName());
    }

    /**
     * Git reset hard origin master.
     */
    public function gitResetHardOriginMaster(): array
    {
        $sMaster = $this->getMasterBranchName();
        $sOrigin = $this->gitOrigin();
        return [
            $this->gitFetch('all'),
            $this->execCmd('reset --hard ' . $sOrigin . '/' . $sMaster),
            $this->gitPull($sOrigin, $sMaster)
        ];
    }

    /**
     * All in one push current then switch to master pull add commit and push.
     */
    public function allInOnePushCurrentThenSwitchToMasterPullAddCommitAndPush(string $sCommitText = ''): array
    {
        if (!$sCommitText) {
            $sCommitText = gmdate('Y-m-d H:i:s') . ' GMT * allInOnePush...';
        }
        $bOnMasterBranch = $this->isOnMasterBranch();
        $aLog = [['MasterBranchPreviouslySelected' => $bOnMasterBranch]];

        $this->gitFetch();
        if (!$bOnMasterBranch) {
            $sCurrentBranch = $this->getCurrentBranchName();
            $aLog = array_merge($aLog, $this->gitAddCommitAndPush(
                'Auto commit [' . $sCurrentBranch . ']' . $sCommitText,
                $sCurrentBranch
            ));
            $aLog[] = $this->checkoutMasterBranch();
        }
        return array_merge($aLog, $this->gitAddCommitAndPush(
            'Auto commit ' . $sCommitText,
            $this->getCurrentBranchName()
        ));
    }

    /**
     * Hook master checkout.
     */
    public function hookMasterCheckout(bool $bSaveCurrentChanges, string $sCommitText = '', bool $bPushToMaster = false): array
    {
        if (!$sCommitText) {
            $sCommitText = gmdate('Y-m-d H:i:s') . ' GMT * ClassicFtpDriven...';
        }
        $this->gitFetch();

        $bOnMasterBranch = $this->isOnMasterBranch();
        $bModifiedOnServer = false;
        $sStatus = implode(' ', $this->gitStatus()['gitOutputLines']);
        if (strpos($sStatus, 'no changes added to commit') !== false) {
            $bModifiedOnServer = true;
        }
        $aLog = [
            ['SaveCurrentChanges' => $bSaveCurrentChanges],
            ['MasterBranchPreviouslySelected' => $bOnMasterBranch],
            ['ModifiedOnServer' => $bModifiedOnServer],
        ];
        if ($bSaveCurrentChanges && $bOnMasterBranch && $bModifiedOnServer && !$bPushToMaster) {
            $this->gitCheckoutNewBranch('FilesOnMasterBranch' . date('ymd-hi'));
        }
        if ($bSaveCurrentChanges && $bModifiedOnServer) {
            $sCurrentBranch = $this->getCurrentBranchName();
            $aLog = array_merge($aLog, $this->gitAddCommitAndPush(
                'Auto commit [' . $sCurrentBranch . ']' . $sCommitText,
                $sCurrentBranch
            ));

        }

        if ($bModifiedOnServer) {
            sleep(2);
            $this->gitFetch();
        }

        $aLog[] = $this->checkoutMasterBranch();
        $aLog[] = $this->execCmd('reset --hard HEAD~1');
        $aLog[] = $this->gitPull();
        return $aLog;
    }

}
