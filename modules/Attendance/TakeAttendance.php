<?php
//FJ move Attendance.php from functions/ to modules/Attendance/includes
require_once 'modules/Attendance/includes/UpdateAttendanceDaily.fnc.php';

// set date
if ( isset( $_REQUEST['month_date'] )
	&& isset( $_REQUEST['day_date'] )
	&& isset( $_REQUEST['year_date'] ) )
{
	$date = RequestedDate(
		$_REQUEST['year_date'],
		$_REQUEST['month_date'],
		$_REQUEST['day_date']
	);
}
else
{
	$_REQUEST['day_date'] = date('d');
	$_REQUEST['month_date'] = date('m');
	$_REQUEST['year_date'] = date('Y');

	$date = $_REQUEST['year_date'] . '-' . $_REQUEST['month_date'] . '-' . $_REQUEST['day_date'];
}

DrawHeader(ProgramTitle());

//FJ bugfix SQL bug more than one row returned by a subquery
$categories_RET = DBGet( DBQuery( "SELECT '0' AS ID,'" . DBEscapeString( _( 'Attendance' ) ) . "' AS TITLE,0,NULL AS SORT_ORDER
	WHERE position(',0,' IN
		(SELECT DOES_ATTENDANCE
		FROM COURSE_PERIODS
		WHERE COURSE_PERIOD_ID='" . UserCoursePeriod() . "')
	)>0
	UNION SELECT ID,TITLE,1,SORT_ORDER
	FROM ATTENDANCE_CODE_CATEGORIES
	WHERE SYEAR='" . UserSyear() . "'
	AND SCHOOL_ID='" . UserSchool() . "'
	AND position(','||ID||',' IN
		(SELECT DOES_ATTENDANCE
		FROM COURSE_PERIODS
		WHERE COURSE_PERIOD_ID='" . UserCoursePeriod() . "')
	)>0
	ORDER BY 3,SORT_ORDER,TITLE" ) );

if (count($categories_RET)==0)
{
	echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&table='.$_REQUEST['table'].'" method="POST">';
	DrawHeader(PrepareDate($date,'_date',false,array('submit'=>true)));
	echo '</form>';

	ErrorMessage(array(_('You cannot take attendance for this course period.')),'fatal');
}

if ($_REQUEST['table']=='')
	$_REQUEST['table'] = $categories_RET[1]['ID'];

if ($_REQUEST['table']=='0')
	$table = 'ATTENDANCE_PERIOD';
else
	$table = 'LUNCH_PERIOD';

//FJ days numbered
//FJ multiple school periods for a course period
if (SchoolInfo('NUMBER_DAYS_ROTATION') !== null)
{
	$course_RET = DBGET(DBQuery("SELECT cp.HALF_DAY
	FROM ATTENDANCE_CALENDAR acc,COURSE_PERIODS cp,SCHOOL_PERIODS sp, COURSE_PERIOD_SCHOOL_PERIODS cpsp
	WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
	AND acc.SYEAR='".UserSyear()."'
	AND cp.SCHOOL_ID=acc.SCHOOL_ID
	AND cp.SYEAR=acc.SYEAR
	AND acc.SCHOOL_DATE='".$date."'
	AND cp.CALENDAR_ID=acc.CALENDAR_ID
	AND cpsp.COURSE_PERIOD_SCHOOL_PERIODS_ID='".UserCoursePeriodSchoolPeriod()."'
	AND cp.MARKING_PERIOD_ID IN (SELECT MARKING_PERIOD_ID FROM SCHOOL_MARKING_PERIODS WHERE (MP='FY' OR MP='SEM' OR MP='QTR')
	AND SCHOOL_ID=acc.SCHOOL_ID
	AND acc.SCHOOL_DATE BETWEEN START_DATE AND END_DATE)
	AND sp.PERIOD_ID=cpsp.PERIOD_ID
	AND (sp.BLOCK IS NULL AND position(substring('MTWHFSU' FROM cast(
		(SELECT CASE COUNT(school_date)% ".SchoolInfo('NUMBER_DAYS_ROTATION')." WHEN 0 THEN ".SchoolInfo('NUMBER_DAYS_ROTATION')." ELSE COUNT(school_date)% ".SchoolInfo('NUMBER_DAYS_ROTATION')." END AS day_number
		FROM attendance_calendar
		WHERE school_date>=(SELECT start_date FROM school_marking_periods WHERE start_date<=acc.SCHOOL_DATE AND end_date>=acc.SCHOOL_DATE AND mp='QTR' AND SCHOOL_ID=acc.SCHOOL_ID)
		AND school_date<=acc.SCHOOL_DATE
		AND SCHOOL_ID=acc.SCHOOL_ID)
	AS INT) FOR 1) IN cpsp.DAYS)>0 OR sp.BLOCK IS NOT NULL AND acc.BLOCK IS NOT NULL AND sp.BLOCK=acc.BLOCK)
	AND position(',".$_REQUEST['table'].",' IN cp.DOES_ATTENDANCE)>0"));
} else {
	$course_RET = DBGET(DBQuery("SELECT cp.HALF_DAY
	FROM ATTENDANCE_CALENDAR acc,COURSE_PERIODS cp,SCHOOL_PERIODS sp, COURSE_PERIOD_SCHOOL_PERIODS cpsp
	WHERE cp.COURSE_PERIOD_ID=cpsp.COURSE_PERIOD_ID
	AND acc.SYEAR='".UserSyear()."'
	AND cp.SCHOOL_ID=acc.SCHOOL_ID
	AND cp.SYEAR=acc.SYEAR
	AND acc.SCHOOL_DATE='".$date."'
	AND cp.CALENDAR_ID=acc.CALENDAR_ID
	AND cpsp.COURSE_PERIOD_SCHOOL_PERIODS_ID='".UserCoursePeriodSchoolPeriod()."'
	AND cp.MARKING_PERIOD_ID IN (SELECT MARKING_PERIOD_ID FROM SCHOOL_MARKING_PERIODS WHERE (MP='FY' OR MP='SEM' OR MP='QTR') AND SCHOOL_ID=acc.SCHOOL_ID AND acc.SCHOOL_DATE BETWEEN START_DATE AND END_DATE)
	AND sp.PERIOD_ID=cpsp.PERIOD_ID
	AND (sp.BLOCK IS NULL AND position(substring('UMTWHFS' FROM cast(extract(DOW FROM acc.SCHOOL_DATE) AS INT)+1 FOR 1) IN cpsp.DAYS)>0 OR sp.BLOCK IS NOT NULL AND acc.BLOCK IS NOT NULL AND sp.BLOCK=acc.BLOCK)
	AND position(',".$_REQUEST['table'].",' IN cp.DOES_ATTENDANCE)>0"));
}
if (count($course_RET)==0)
{
	echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&table='.$_REQUEST['table'].'" method="POST">';
	DrawHeader(PrepareDate($date,'_date',false,array('submit'=>true)));
	echo '</form>';

	ErrorMessage(array(_('You cannot take attendance for this period on this day.')),'fatal');
}

$qtr_id = GetCurrentMP('QTR',$date,false);
if (! $qtr_id)
{
	echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&table='.$_REQUEST['table'].'" method="POST">';
	DrawHeader(PrepareDate($date,'_date',false,array('submit'=>true)));
	echo '</form>';

	ErrorMessage(array(_('The selected date is not in a school quarter.')),'fatal');
}

// if running as a teacher program then rosario[allow_edit] will already be set according to admin permissions
if (!isset($_ROSARIO['allow_edit']))
{
	// allow teacher edit if selected date is in the current quarter or in the corresponding grade posting period
	$current_qtr_id = GetCurrentMP('QTR',DBDate(),false);

	$time = strtotime( DBDate() );

	if ( ($current_qtr_id
			&& $qtr_id == $current_qtr_id
			|| GetMP( $qtr_id, 'POST_START_DATE' )
			&& DBDate() <= GetMP( $qtr_id, 'POST_END_DATE' ) )
		&& ( !ProgramConfig( 'attendance', 'ATTENDANCE_EDIT_DAYS_BEFORE' )
			|| strtotime( $date ) <= $time + ProgramConfig( 'attendance', 'ATTENDANCE_EDIT_DAYS_BEFORE' ) * 86400 )
		&& ( !ProgramConfig( 'attendance', 'ATTENDANCE_EDIT_DAYS_AFTER' )
			|| strtotime( $date ) >= $time - ProgramConfig( 'attendance', 'ATTENDANCE_EDIT_DAYS_AFTER' ) * 86400 ) )
	{
		$_ROSARIO['allow_edit'] = true;
	}
}

$current_Q = "SELECT ATTENDANCE_TEACHER_CODE,STUDENT_ID,ADMIN,COMMENT,COURSE_PERIOD_ID,ATTENDANCE_REASON FROM ".$table." t WHERE SCHOOL_DATE='".$date."' AND PERIOD_ID='".UserPeriod()."'".($table=='LUNCH_PERIOD'?" AND TABLE_NAME='".$_REQUEST['table']."'":'');

$current_RET = DBGet(DBQuery($current_Q),array(),array('STUDENT_ID'));

if ($_REQUEST['attendance'] && $_POST['attendance'])
{
	foreach ( (array) $_REQUEST['attendance'] as $student_id => $value)
	{
		if ($current_RET[ $student_id ])
		{
			$sql = "UPDATE $table SET ATTENDANCE_TEACHER_CODE='".mb_substr($value,5)."',COURSE_PERIOD_ID='".UserCoursePeriod()."'";

			if ($current_RET[ $student_id ][1]['ADMIN']!='Y')
				$sql .= ",ATTENDANCE_CODE='".mb_substr($value,5)."'";

			if ($_REQUEST['comment'][ $student_id ])
				$sql .= ",COMMENT='".trim($_REQUEST['comment'][ $student_id ])."'";

			$sql .= " WHERE SCHOOL_DATE='".$date."' AND PERIOD_ID='".UserPeriod()."' AND STUDENT_ID='".$student_id."'";
		}
		else
			$sql = "INSERT INTO ".$table." (STUDENT_ID,SCHOOL_DATE,MARKING_PERIOD_ID,PERIOD_ID,COURSE_PERIOD_ID,ATTENDANCE_CODE,ATTENDANCE_TEACHER_CODE,COMMENT".($table=='LUNCH_PERIOD'?',TABLE_NAME':'').") values('".$student_id."','".$date."','".$qtr_id."','".UserPeriod()."','".UserCoursePeriod()."','".mb_substr($value,5)."','".mb_substr($value,5)."','".$_REQUEST['comment'][ $student_id ]."'".($table=='LUNCH_PERIOD'?",'".$_REQUEST['table']."'":'').")";

		DBQuery($sql);

		if ($_REQUEST['table']=='0')
			UpdateAttendanceDaily($student_id,$date);
	}
	$RET = DBGet(DBQuery("SELECT 'Y' AS COMPLETED FROM ATTENDANCE_COMPLETED WHERE STAFF_ID='".User('STAFF_ID')."' AND SCHOOL_DATE='".$date."' AND PERIOD_ID='".UserPeriod()."' AND TABLE_NAME='".$_REQUEST['table']."'"));

	if (!count($RET))
		DBQuery("INSERT INTO ATTENDANCE_COMPLETED (STAFF_ID,SCHOOL_DATE,PERIOD_ID,TABLE_NAME) values('".User('STAFF_ID')."','".$date."','".UserPeriod()."','".$_REQUEST['table']."')");

	$current_RET = DBGet(DBQuery($current_Q),array(),array('STUDENT_ID'));
	unset($_SESSION['_REQUEST_vars']['attendance']);
}

$codes_RET = DBGet(DBQuery("SELECT ID,TITLE,DEFAULT_CODE,STATE_CODE
FROM ATTENDANCE_CODES
WHERE SCHOOL_ID='".UserSchool()."'
AND SYEAR='".UserSyear()."'
AND TYPE = 'teacher'
AND TABLE_NAME='".$_REQUEST['table']."'".
($_REQUEST['table']=='0' && $course_RET[1]['HALF_DAY'] ? " AND STATE_CODE!='H'" : '')."
ORDER BY SORT_ORDER"));

if (count($codes_RET))
{
	foreach ( (array) $codes_RET as $code)
	{
		$extra['SELECT'] .= ",'".$code['STATE_CODE']."' AS CODE_".$code['ID'];

		if ($code['DEFAULT_CODE']=='Y')
			$extra['functions']['CODE_'.$code['ID']] = '_makeRadioSelected';
		else
			$extra['functions']['CODE_'.$code['ID']] = '_makeRadio';

		$columns['CODE_'.$code['ID']] = $code['TITLE'];
	}
}
else
	$columns = array();

$extra['SELECT'] .= ',s.STUDENT_ID AS COMMENT,s.STUDENT_ID AS ATTENDANCE_REASON';
$columns += array('COMMENT' => _('Teacher Comment'));

if (!isset($extra['functions']) || !is_array($extra['functions']))
	$extra['functions'] = array();

$extra['functions'] += array('FULL_NAME' => '_makeTipMessage','COMMENT' => 'makeCommentInput','ATTENDANCE_REASON' => 'makeAttendanceReason');
$extra['DATE'] = $date;

$stu_RET = GetStuList($extra);

if ($attendance_reason)
	$columns += array('ATTENDANCE_REASON' => _('Office Comment'));

$date_note = $date!=DBDate() ? ' <span style="color:red" class="nobr">'._('The selected date is not today').'</span> |' : '';
$date_note .= AllowEdit() ? ' <span style="color:green" class="nobr">'._('You can edit this attendance').'</span>':' <span style="color:red" class="nobr">'._('You cannot edit this attendance').'</span>';

$completed_RET = DBGet(DBQuery("SELECT 'Y' as COMPLETED FROM ATTENDANCE_COMPLETED WHERE STAFF_ID='".User('STAFF_ID')."' AND SCHOOL_DATE='".$date."' AND PERIOD_ID='".UserPeriod()."' AND TABLE_NAME='".$_REQUEST['table']."'"));

if (count($completed_RET))
	$note[] = button('check') .'&nbsp;'._('You already have taken attendance today for this period.');

echo '<form action="Modules.php?modname='.$_REQUEST['modname'].'&table='.$_REQUEST['table'].'" method="POST">';

DrawHeader('',SubmitButton(_('Save')));
DrawHeader(PrepareDate($date,'_date',false,array('submit'=>true)).$date_note);
//DrawHeader($note);

echo ErrorMessage( $note, 'note' );

$LO_columns = array('FULL_NAME' => _('Student'),'STUDENT_ID'=>sprintf(_('%s ID'),Config('NAME')),'GRADE_ID' => _('Grade Level')) + $columns;

foreach ( (array) $categories_RET as $category)
	$tabs[] = array('title'=>ParseMLField($category['TITLE']),'link' => 'Modules.php?modname='.$_REQUEST['modname'].'&table='.$category['ID'].'&month_date='.$_REQUEST['month_date'].'&day_date='.$_REQUEST['day_date'].'&year_date='.$_REQUEST['year_date']);

echo '<br />';
if (count($categories_RET))
    $LO_options = array('download'=>false,'search'=>false,'header'=>WrapTabs($tabs,'Modules.php?modname='.$_REQUEST['modname'].'&table='.$_REQUEST['table'].'&month_date='.$_REQUEST['month_date'].'&day_date='.$_REQUEST['day_date'].'&year_date='.$_REQUEST['year_date']));
else
    $LO_options = array();

ListOutput($stu_RET,$LO_columns,'Student','Students',false,array(),$LO_options);

echo '<br /><div class="center">' . SubmitButton( _( 'Save' ) ) . '</div>';
echo '</form>';

function _makeRadio($value,$title)
{	global $THIS_RET,$current_RET;

	$colors = array('P' => '#00FF00','A' => '#FF0000','H' => '#FFCC00','T' => '#0000FF');
	if ($current_RET[$THIS_RET['STUDENT_ID']][1]['ATTENDANCE_TEACHER_CODE']==mb_substr($title,5))
	{
		if (isset($_REQUEST['LO_save']))
			return _('Yes');
		else
			return '<div style="'.($current_RET[$THIS_RET['STUDENT_ID']][1]['COURSE_PERIOD_ID']==UserCoursePeriod()?($colors[ $value ]?'background-color:'.$colors[ $value ].';':''):'background-color:#000;').' float:left; padding: 2px 8px;"><input type="radio" name="attendance['.$THIS_RET['STUDENT_ID'].']" value="'.$title.'" checked /></div>';
	}
	else
		return '<div style="float:left; padding: 2px 8px;"><input type="radio" name="attendance['.$THIS_RET['STUDENT_ID'].']" value="'.$title.'"'.(AllowEdit()?'':' disabled').'></div>';
}

function _makeRadioSelected($value,$title)
{	global $THIS_RET,$current_RET;

	$colors = array('P' => '#00FF00','A' => '#FF0000','H' => '#FFCC00','T' => '#0000FF');
	$colors1 = array('P' => '#DDFFDD','A' => '#FFDDDD','H' => '#FFEEDD','T' => '#DDDDFF');
	if ($current_RET[$THIS_RET['STUDENT_ID']][1]['ATTENDANCE_TEACHER_CODE']!='')
		if ($current_RET[$THIS_RET['STUDENT_ID']][1]['ATTENDANCE_TEACHER_CODE']==mb_substr($title,5))
		{
			if (isset($_REQUEST['LO_save']))
				return _('Yes');
			else
				return '<div style="'.($current_RET[$THIS_RET['STUDENT_ID']][1]['COURSE_PERIOD_ID']==UserCoursePeriod()?($colors[ $value ]?'background-color:'.$colors[ $value ].';':''):'background-color:#000;').' float:left; padding: 2px 8px;"><input type="radio" name="attendance['.$THIS_RET['STUDENT_ID'].']" value="'.$title.'" checked /></div>';
		}
		else
			return '<div style="float:left; padding: 2px 8px;"><input type="radio" name="attendance['.$THIS_RET['STUDENT_ID'].']" value="'.$title.'"'.(AllowEdit()?'':' disabled').'></div>';
	else
	{
		if (isset($_REQUEST['LO_save']))
			return _('Yes');
		else
			return '<div style="'.($colors1[ $value ]?'background-color:'.$colors1[ $value ].';':'').'; float:left;">&nbsp;&nbsp;<input type="radio" name="attendance['.$THIS_RET['STUDENT_ID'].']" value="'.$title.'" checked />&nbsp;&nbsp;</div>';
	}
}


/**
 * Make Tip Message containing Student Photo
 * Local function
 *
 * Callback for DBGet() column formatting
 *
 * @uses MakeStudentPhotoTipMessage()
 *
 * @see ProgramFunctions/TipMessage.fnc.php
 *
 * @global $THIS_RET, see DBGet()
 *
 * @param  string $full_name Student Full Name
 * @param  string $column    'FULL_NAME'
 *
 * @return string Student Full Name + Tip Message containing Student Photo
 */
function _makeTipMessage( $full_name, $column )
{
	global $THIS_RET;

	require_once 'ProgramFunctions/TipMessage.fnc.php';

	return MakeStudentPhotoTipMessage( $THIS_RET['STUDENT_ID'], $full_name );
}

function makeCommentInput($student_id,$column)
{	global $current_RET;

	return TextInput($current_RET[ $student_id ][1]['COMMENT'],'comment['.$student_id.']','','maxlength="100" size="20"',true,true);
}

function makeAttendanceReason($student_id,$column)
{	global $current_RET,$attendance_reason;

	if ($current_RET[ $student_id ][1]['ATTENDANCE_REASON'])
	{
		$attendance_reason = true;
		return $current_RET[ $student_id ][1]['ATTENDANCE_REASON'];
	}
}