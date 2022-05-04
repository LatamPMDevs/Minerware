# Minerware Contribution Guidelines

Minerware is an open source project, and contributions from the community are welcomed, as long as they comply with our quality standards and licensing.

Code contributions must be submitted using [GitHub Pull Requests](https://github.com/LatamPMDevs/Minerware/pulls), where they will be reviewed by maintainers.

## Useful documentation from github.com
- [About pull requests](https://docs.github.com/en/github/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/about-pull-requests)
- [About forks](https://docs.github.com/en/github/collaborating-with-pull-requests/working-with-forks/about-forks)
- [Creating a pull request from a fork](https://docs.github.com/en/github/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/creating-a-pull-request-from-a-fork)

## Other things you'll need
- [git](https://git-scm.com/)

## Making a pull request
The basic procedure to create a pull request is:
1. [Fork the repository on GitHub](https://github.com/LatamPMDevs/Minerware/fork). This gives you your own copy of the repository to make changes to.
2. Create a branch on your fork for your changes.
3. Make the changes you want to make on this branch.
4. You can then make a [pull request](https://github.com/LatamPMDevs/Minerware/pull/new) to the project.

## Pull request reviews
Pull requests will be reviewed by maintainers when they are available.
Note that there might be a long wait time before a reviewer looks at your PR.

Depending on the changes, maintainers might ask you to make changes to the PR to fix problems or to improve the code.
**Do not delete your fork** while your pull request remains open, otherwise you won't be able to make any requested changes and the PR will end up being declined.

### Requirements
The following are required as a minimum for pull requests. PRs that don't meet these requirements will be declined unless updated to meet them.

#### Licensing
Minerware is licensed under [GPLv3 license](LICENSE).
By proposing a pull request, you agree to your code being distributed within Minerware under the same license.
If you take code from other projects, that code MUST be licensed under an GPL-compatible license.

#### PRs should be about exactly ONE thing
If you want to make multiple changes, those changes should each be contributed as separate pull requests. **DO NOT** mix unrelated changes.

#### PRs must not include unnecessary/unrelated changes
Do not include changes which aren't strictly necessary. This makes it harder to review a PR, because the code diff becomes larger and harder to review.
This means:
- don't reformat or rearrange existing code
- don't change things that aren't related to the PR's objective
- don't rewrite existing code just to make it "look nicer"
- don't change PhpDocs to native types in code you didn't write

## Translations
To change or add new translations you will need to create a PR as mentioned above.
