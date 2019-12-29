var didSavePost = false;
if (admin_info) {
  try {
    const { removeEditorPanel } = wp.data.dispatch('core/edit-post');

    removeEditorPanel('taxonomy-panel-category');
    removeEditorPanel('taxonomy-panel-post_tag');
    removeEditorPanel('discussion-panel');
  } catch(error) {
  }
}
wp.data.subscribe(function () {
  try {
    var isSavingPost = wp.data.select('core/editor').isSavingPost();
    var isAutosavingPost = wp.data.select('core/editor').isAutosavingPost();
    var success = wp.data.select('core/editor').didPostSaveRequestSucceed();
    var isDirty = wp.data.select("core/editor").isEditedPostDirty();

    if (isSavingPost) {
      didSavePost = true;
    }
    if (success && didSavePost && !isDirty && !isAutosavingPost) {
      setTimeout(function (){
       window.location = "admin.php?page=robera-edit-test&test=" + admin_info.test_id;
      }, 100);
    }
  } catch(error) {
  }
})

