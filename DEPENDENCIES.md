The `sharkk/router` dependency uses the public recovery mirror at
https://github.com/Valgorithms/sharkk-router because its original source,
https://git.sharkk.net/php/router, was unreachable during CI installation.

The mirror preserves the upstream Git history, MIT license, and exact pinned
commit `2e6c969cb2ffb23c4a042a3d703939849ee73789`. Its tree is
`565ec95eba94c9bdd7d9eef0d65b19ede380ae9a`. No router code was modified for
this recovery. Composer's VCS source points at the mirror; the package name,
namespace, and revision requirement remain unchanged.
