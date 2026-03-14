Namespace:
- Autoframe\\Core\\FileSystem

SINGLETON Classes:
- AfrFileSystemCollectionClass (contains all the methods from the next classes)
- AfrDirPathClass
  -  isDir
  -  openDir
  -  detectDirectorySeparatorFromPath
  -  getApplicableSlashStyle
  -  removeFinalSlash
  -  addFinalSlash
  -  makeUniformSlashStyle
  -  correctPathFormat
  -  simplifyAbsolutePath
  -  fixDs

- AfrBase64InlineDataClass
  - getBase64InlineData
  
- AfrOverWriteClass
  - overWriteFile
 
- AfrDirTraversingCollectionClass (all traversing methods)
- AfrDirTraversingCountChildrenDirsClass
  - countAllChildrenDirs
- AfrDirTraversingFileListClass
  - getDirFileList
- AfrDirTraversingGetAllChildrenDirsClass
  - getAllChildrenDirs

- AfrDirMaxFileMtimeClass
  - getDirMaxFileMtime
- AfrFileVersioningMtimeHashClass
  - fileVersioningMtimeHash
- AfrSplitMergeClass
  - AfrSplitMergeInterface
- AfrSplitMergeCopyDirClass
  - AfrSplitMergeCopyDirInterface

Includes:
- Traits (can be used for embedding into classes if the singleton is not good enough)
- Interfaces