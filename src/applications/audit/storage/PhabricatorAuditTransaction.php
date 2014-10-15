<?php

final class PhabricatorAuditTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_COMMIT = 'audit:commit';

  public function getApplicationName() {
    return 'audit';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryCommitPHIDType::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return new PhabricatorAuditTransactionComment();
  }

  public function getRemarkupBlocks() {
    $blocks = parent::getRemarkupBlocks();

    switch ($this->getTransactionType()) {
    case self::TYPE_COMMIT:
      $data = $this->getNewValue();
      $blocks[] = $data['description'];
      break;
    }

    return $blocks;
  }

  public function getRequiredHandlePHIDs() {
    $phids = parent::getRequiredHandlePHIDs();

    $type = $this->getTransactionType();

    switch ($type) {
      case self::TYPE_COMMIT:
        $phids[] = $this->getObjectPHID();
        $data = $this->getNewValue();
        if ($data['authorPHID']) {
          $phids[] = $data['authorPHID'];
        }
        if ($data['committerPHID']) {
          $phids[] = $data['committerPHID'];
        }
        break;
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $old = $this->getOldValue();
        $new = $this->getNewValue();

        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }

        foreach (array_keys($old + $new) as $phid) {
          $phids[] = $phid;
        }
        break;
    }

    return $phids;
  }

  public function getActionName() {

    switch ($this->getTransactionType()) {
      case PhabricatorAuditActionConstants::ACTION:
        switch ($this->getNewValue()) {
          case PhabricatorAuditActionConstants::CONCERN:
            return pht('Raised Concern');
          case PhabricatorAuditActionConstants::ACCEPT:
            return pht('Accepted');
          case PhabricatorAuditActionConstants::RESIGN:
            return pht('Resigned');
          case PhabricatorAuditActionConstants::CLOSE:
            return pht('Closed');
        }
        break;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return pht('Added Auditors');
      case self::TYPE_COMMIT:
        return pht('Committed');
    }

    return parent::getActionName();
  }

  public function getColor() {

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ACTION:
        switch ($this->getNewValue()) {
          case PhabricatorAuditActionConstants::CONCERN:
            return 'red';
          case PhabricatorAuditActionConstants::ACCEPT:
            return 'green';
        }
    }

    return parent::getColor();
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_handle = $this->renderHandleLink($this->getAuthorPHID());

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }
        $add = array_keys(array_diff_key($new, $old));
        $rem = array_keys(array_diff_key($old, $new));
        break;
    }

    switch ($type) {
      case self::TYPE_COMMIT:
        $author = null;
        if ($new['authorPHID']) {
          $author = $this->renderHandleLink($new['authorPHID']);
        } else {
          $author = $new['authorName'];
        }

        $committer = null;
        if ($new['committerPHID']) {
          $committer = $this->renderHandleLink($new['committerPHID']);
        } else if ($new['committerName']) {
          $committer = $new['committerName'];
        }

        $commit = $this->renderHandleLink($this->getObjectPHID());

        if (!$committer) {
          $committer = $author;
          $author = null;
        }

        if ($author) {
          $title = pht(
            '%s committed %s (authored by %s).',
            $committer,
            $commit,
            $author);
        } else {
          $title = pht(
            '%s committed %s.',
            $committer,
            $commit);
        }
        return $title;

      case PhabricatorAuditActionConstants::INLINE:
        return pht(
          '%s added inline comments.',
          $author_handle);

      case PhabricatorAuditActionConstants::ADD_CCS:
        if ($add && $rem) {
          return pht(
            '%s edited subscribers; added: %s, removed: %s.',
            $author_handle,
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added subscribers: %s.',
            $author_handle,
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed subscribers: %s.',
            $author_handle,
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s added subscribers...',
            $author_handle);
        }

      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if ($add && $rem) {
          return pht(
            '%s edited auditors; added: %s, removed: %s.',
            $author_handle,
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added auditors: %s.',
            $author_handle,
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed auditors: %s.',
            $author_handle,
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s added auditors...',
            $author_handle);
        }

      case PhabricatorAuditActionConstants::ACTION:
        switch ($new) {
          case PhabricatorAuditActionConstants::ACCEPT:
            return pht(
              '%s accepted this commit.',
              $author_handle);
          case PhabricatorAuditActionConstants::CONCERN:
            return pht(
              '%s raised a concern with this commit.',
              $author_handle);
          case PhabricatorAuditActionConstants::RESIGN:
            return pht(
              '%s resigned from this audit.',
              $author_handle);
          case PhabricatorAuditActionConstants::CLOSE:
            return pht(
              '%s closed this audit.',
              $author_handle);
        }

    }

    return parent::getTitle();
  }

  public function getTitleForFeed(PhabricatorFeedStory $story) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $author_handle = $this->renderHandleLink($this->getAuthorPHID());
    $object_handle = $this->renderHandleLink($this->getObjectPHID());

    $type = $this->getTransactionType();

    switch ($type) {
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if (!is_array($old)) {
          $old = array();
        }
        if (!is_array($new)) {
          $new = array();
        }
        $add = array_keys(array_diff_key($new, $old));
        $rem = array_keys(array_diff_key($old, $new));
        break;
    }

    switch ($type) {
      case self::TYPE_COMMIT:
        $author = null;
        if ($new['authorPHID']) {
          $author = $this->renderHandleLink($new['authorPHID']);
        } else {
          $author = $new['authorName'];
        }

        $committer = null;
        if ($new['committerPHID']) {
          $committer = $this->renderHandleLink($new['committerPHID']);
        } else if ($new['committerName']) {
          $committer = $new['committerName'];
        }

        if (!$committer) {
          $committer = $author;
          $author = null;
        }

        if ($author) {
          $title = pht(
            '%s committed %s (authored by %s).',
            $committer,
            $object_handle,
            $author);
        } else {
          $title = pht(
            '%s committed %s.',
            $committer,
            $object_handle);
        }
        return $title;

      case PhabricatorAuditActionConstants::INLINE:
        return pht(
          '%s added inline comments to %s.',
          $author_handle,
          $object_handle);

      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        if ($add && $rem) {
          return pht(
            '%s edited auditors for %s; added: %s, removed: %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleList($add),
            $this->renderHandleList($rem));
        } else if ($add) {
          return pht(
            '%s added auditors to %s: %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleList($add));
        } else if ($rem) {
          return pht(
            '%s removed auditors from %s: %s.',
            $author_handle,
            $object_handle,
            $this->renderHandleList($rem));
        } else {
          return pht(
            '%s added auditors to %s...',
            $author_handle,
            $object_handle);
        }

      case PhabricatorAuditActionConstants::ACTION:
        switch ($new) {
          case PhabricatorAuditActionConstants::ACCEPT:
            return pht(
              '%s accepted %s.',
              $author_handle,
              $object_handle);
          case PhabricatorAuditActionConstants::CONCERN:
            return pht(
              '%s raised a concern with %s.',
              $author_handle,
              $object_handle);
          case PhabricatorAuditActionConstants::RESIGN:
            return pht(
              '%s resigned from auditing %s.',
              $author_handle,
              $object_handle);
          case PhabricatorAuditActionConstants::CLOSE:
            return pht(
              '%s closed the audit of %s.',
              $author_handle,
              $object_handle);
        }

    }

    return parent::getTitleForFeed($story);
  }

  public function getBodyForFeed(PhabricatorFeedStory $story) {
    switch ($this->getTransactionType()) {
      case self::TYPE_COMMIT:
        $data = $this->getNewValue();
        return $story->renderSummary($data['summary']);
    }
    return parent::getBodyForFeed($story);
  }


  // TODO: These two mail methods can likely be abstracted by introducing a
  // formal concept of "inline comment" transactions.

  public function shouldHideForMail(array $xactions) {
    $type_inline = PhabricatorAuditActionConstants::INLINE;
    switch ($this->getTransactionType()) {
      case $type_inline:
        foreach ($xactions as $xaction) {
          if ($xaction->getTransactionType() != $type_inline) {
            return true;
          }
        }
        return ($this !== head($xactions));
    }

    return parent::shouldHideForMail($xactions);
  }

  public function getBodyForMail() {
    switch ($this->getTransactionType()) {
      case PhabricatorAuditActionConstants::INLINE:
        return null;
      case self::TYPE_COMMIT:
        $data = $this->getNewValue();
        return $data['description'];
    }

    return parent::getBodyForMail();
  }

}
