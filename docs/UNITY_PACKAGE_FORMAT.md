<root>
  ├── package.json
  ├── README.md
  ├── CHANGELOG.md
  ├── LICENSE.md
  ├── Editor
  │   ├── Unity.[YourPackageName].Editor.asmdef
  │   └── EditorExample.cs
  ├── Runtime
  │   ├── Unity.[YourPackageName].asmdef
  │   └── RuntimeExample.cs
  ├── Tests
  │   ├── Editor
  │   │   ├── Unity.[YourPackageName].Editor.Tests.asmdef
  │   │   └── EditorExampleTest.cs
  │   └── Runtime
  │        ├── Unity.[YourPackageName].Tests.asmdef
  │        └── RuntimeExampleTest.cs
  └── Documentation~
       └── [YourPackageName].md


https://docs.unity3d.com/2019.1/Documentation/Manual/cus-layout.html
https://docs.unity3d.com/2019.1/Documentation/Manual/upm-manifestPkg.html


Location	Description
package.json	The package manifest
, which defines the package dependencies and other metadata.
README.md	Developer package documentation. This is generally documentation to help developers who want to modify the package or push a new change on the package master source repository.
CHANGELOG.md	Description of package changes in reverse chronological order. It is good practice to use a standard format, like Keep a Changelog.
LICENSE.md	Contains the package license text. Usually the Package Manager copies the text from the selected SPDX list website.
Editor/	Editor platform-specific Assets folder. Unlike Editor folders under Assets, this is only a convention and does not affect the Asset import pipeline. See Assembly definition and packages to properly configure Editor-specific assemblies in this folder.
Runtime/	Runtime platform-specific Assets folder. This is only a convention and does not affect the Asset import pipeline. See Assembly definition and packages to properly configure runtime assemblies in this folder.
Tests/	Package tests folder.
Tests/Editor/	Editor platform specific tests folder. See Assembly definition and packages to properly configure Editor-specific test assemblies in this folder.
Tests/Runtime/	Runtime platform specific tests. See Assembly definition and packages to properly configure runtime test assemblies in this folder.
Documentation~	Optional folder for documentation for the package.