# Matching questions

Matching questions require candidates to associate each answer with one item
from a numbered list. In the database and import/export formats they use
question type `5`.

## Creating a matching question

1. In the question editor, select **Matching answer**.
2. Put the numbered source items in the question text, for example:

   ```text
   1. France
   2. Italy
   3. Spain
   ```

3. Create one answer for each target item and set its correct position:

   | Answer | Position |
   |---|---:|
   | Paris | 1 |
   | Rome | 2 |
   | Madrid | 3 |

Enabled answers must use distinct positive positions. A valid matching
question contains at least two enabled answers.

During a test, the candidate selects a source position for every answer. A
position can be assigned only once; assigning it again clears the previous
selection. An empty selection represents an unanswered pair.

## Scoring

When partial scoring is enabled, each pair is scored independently and the
sum is divided by the number of pairs. Otherwise the question receives its
full score only when every pair is correct.

Before publishing a test, verify a completely correct response, a response
with one swapped pair, an omitted pair, and an attempt to reuse a position.
