# Public repository contents

Publish application code, generic administration instructions and reproducible tests
using synthetic data. Keep raw incident reports, production request/session/attempt
identifiers, participant data, database extracts, credentials, host inventories and
operator access instructions in private storage outside this checkout.

Before publishing an investigation, extract its general technical findings and replace
all examples with synthetic data. Removing names alone does not anonymize linked
attempt identifiers and precise event histories. Review generated artifacts and Git
history as well as the current files. `.gitignore` does not remove tracked history.

After a history cleanup, collaborators must use fresh clones or carefully transplant
unpublished changes. Never merge or force-push the old history back into this repository.
