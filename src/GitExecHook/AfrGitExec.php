<?php
declare(strict_types=1);

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

/**
 * Lightweight wrapper around common Git CLI automation flows.
 *
 * Features:
 * - executes git commands against a target repository,
 * - offers convenience methods for fetch/pull/add/commit/push,
 * - supports branch-oriented automation for webhook-driven deployments.
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
     *
     * @throws Exception
     */
    public function __construct(
        string $gitRepoDir,
        string $gitExePath = 'git',
        string $gitOrigin = 'origin',
        bool   $bUsePathNotCArg = false
    ) {
        if (!$gitRepoDir) {
            $sEnvConstant = 'X_PATH_TO_GIT_REPO_DIR';
            $gitRepoDir = defined($sEnvConstant) ? (string)constant($sEnvConstant) : (string)($_ENV[$sEnvConstant] ?? '');
        }

        $this->gitRepoDir($gitRepoDir);
        $this->gitExePath($gitExePath);
        $this->gitOrigin($gitOrigin);

        $aVersionInfo = $this->execCmd('--version', false);
        if (empty($aVersionInfo['gitExitSuccess'])) {
            throw new Exception('Git is not installed on current system or misconfigured or PHP exec() is not permitted!');
        }

        if (!empty($aVersionInfo['execReturn']) && preg_match('/(\d+)\.(\d+)(?:\.(\d+))?/', (string)$aVersionInfo['execReturn'], $m)) {
            $major = (int)($m[1] ?? 0);
            $minor = (int)($m[2] ?? 0);
            $patch = (int)($m[3] ?? 0);
            $this->fV = (float)($major . '.' . $minor . $patch);
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
    ): array {
        $sCmd = $this->gitExePath();
        if ($withGitRepoDir) {
            if ($this->bOldGitPathVersion) {
                $sCmd = 'cd ' . $this->gitRepoDir . ' && ' . $sCmd;
            } else {
                $sCmd .= ' -C ' . $this->gitRepoDir;
            }
        }
        $sCmd .= ' ' . $sGitArgs . ($b21 ? ' 2>&1' : '');

        $gitOutputLines = [];
        $gitExitCode = 1;
        $execReturn = exec($sCmd, $gitOutputLines, $gitExitCode);

        if (static::$iSleepMsBetweenExecs > 0) {
            usleep(static::$iSleepMsBetweenExecs * 1000);
        }
        return [
            'gitExitSuccess' => $gitExitCode === 0,
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
    ): array {
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
     */
    public function gitCloneWithUserToken(
        string $sRepoUrl,
        string $sUsername = '',
        string $sClassicToken = '',
        string $sMoreArgs = ''
    ): array {
        if (strpos($sRepoUrl, '@') === false && $sUsername && $sClassicToken) {
            $aUrl = explode('//', $sRepoUrl);
            $aUrl[1] = urlencode($sUsername) . ':' . urlencode($sClassicToken) . '@' . $aUrl[1];
            $sRepoUrl = implode('//', $aUrl);
        }

        return $this->execCmd('clone ' . $sMoreArgs . $sRepoUrl . ' ' . $this->gitRepoDir, false);
    }

    public function gitRevertChangesFromWorkingCopy(): array
    {
        return $this->execCmd('checkout .');
    }

    public function gitResetChangesToIndexAndUnpushedCommits(bool $hard = false): array
    {
        return $this->execCmd('reset' . ($hard ? ' --hard' : ''));
    }

    public function gitResetHardCachedIndexes(): array
    {
        return [
            $this->execCmd('rm --cached -r .'),
            $this->gitResetChangesToIndexAndUnpushedCommits(true),
        ];
    }

    public function gitCleanUntrackedFilesDirectories(
        bool $files = true,
        bool $directories = true,
        bool $quiet = false
    ): array {
        $sFlags = $files ? 'f' : '';
        $sFlags .= $directories ? 'd' : '';
        $sFlags .= $quiet ? 'q' : '';
        if ($sFlags) {
            $sFlags = ' -' . $sFlags;
        }
        return $this->execCmd('clean' . $sFlags);
    }

    public function gitRevertCommit12(string $sCommit1, string $sCommit2): array
    {
        return $this->execCmd('revert ' . $sCommit1 . ' ' . $sCommit2);
    }

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

    public function gitPullForce(string $remote = ''): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return $this->execCmd('pull -f ' . $remote);
    }

    public function gitFetch(string $remote = '--all'): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return $this->execCmd('fetch ' . $remote);
    }

    public function gitDiff(string $args = ''): array
    {
        return $this->execCmd(trim('diff ' . $args));
    }

    public function gitStatus(string $args = ''): array
    {
        return $this->execCmd(trim('status ' . $args));
    }

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

    public function gitPush(string $sBranch, string $remote = '', string $gitArgs = '-u'): array
    {
        if (!$remote) {
            $remote = $this->gitOrigin();
        }
        return $this->execCmd('push ' . ($gitArgs ? trim($gitArgs) . ' ' : '') . $remote . ' ' . $sBranch);
    }

    public function gitAddAndCommit(string $sCommitMessage): array
    {
        return [
            $this->execCmd('add .'),
            $this->execCmd('commit -m "' . $sCommitMessage . '"'),
        ];
    }

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

    public function gitCheckoutNewBranch(string $sBranch): array
    {
        return $this->execCmd('checkout -b ' . $sBranch);
    }

    public function gitCheckoutBranch(string $sBranch): array
    {
        return $this->execCmd('checkout ' . $sBranch);
    }

    public function getCurrentBranchName(): string
    {
        if ($this->fV < 2) {
            $aBranches = $this->getBranchList();
            return (string)($aBranches[0] ?? '');
        }
        $sCurrent = trim((string)$this->execCmd('branch --show-current')['execReturn']);
        if ($sCurrent !== '') {
            return $sCurrent;
        }
        $aBranches = $this->getBranchList();
        return (string)($aBranches[0] ?? '');
    }

    public function getBranchList(): array
    {
        $aBranches = [];
        $sSelected = '';

        $aLines = $this->execCmd('branch')['gitOutputLines'];
        if (!is_array($aLines)) {
            $aLines = [$aLines];
        }
        foreach ($aLines as $aLine) {
            $aLine = trim((string)$aLine);
            if ($aLine === '') {
                continue;
            }
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

    public function getMasterBranchName(): ?string
    {
        if (count(self::$defaultMasterBranchNames) === 1) {
            return self::$defaultMasterBranchNames[0];
        }

        foreach ($this->getBranchList() as $sBranch) {
            if (in_array($sBranch, self::$defaultMasterBranchNames, true)) {
                return $sBranch;
            }
        }
        return null;
    }

    public function isOnMasterBranch(): bool
    {
        return in_array($this->getCurrentBranchName(), self::$defaultMasterBranchNames, true);
    }

    public function checkoutMasterBranch(): array
    {
        $sMaster = $this->getMasterBranchName();
        if ($sMaster === null || $sMaster === '') {
            return [
                'gitExitSuccess' => false,
                'gitExitCode' => 1,
                'gitOutputLines' => ['Unable to detect master branch name.'],
                'execReturn' => '',
                'call' => 'checkout <master>',
            ];
        }
        return $this->gitCheckoutBranch($sMaster);
    }

    public function gitResetHardOriginMaster(): array
    {
        $sMaster = $this->getMasterBranchName();
        $sOrigin = $this->gitOrigin();
        return [
            $this->gitFetch('--all'),
            $this->execCmd('reset --hard ' . $sOrigin . '/' . $sMaster),
            $this->gitPull($sOrigin, (string)$sMaster)
        ];
    }

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
