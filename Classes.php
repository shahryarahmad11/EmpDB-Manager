<?php
/*
 * classes.php
 * Contains one class per database table.
 * Each class holds the table's fields as properties
 * and includes basic methods for getting and setting data.
 * Based on OOP concepts from Lecture 5 & 6.
 */

/* -----------------------------------------------
   Class for the DEPT table
----------------------------------------------- */
class Dept {
    public $deptno;
    public $dname;
    public $loc;

    public function __construct($deptno = null, $dname = null, $loc = null) {
        $this->deptno = $deptno;
        $this->dname  = $dname;
        $this->loc    = $loc;
    }

    public function get_deptno() { return $this->deptno; }
    public function get_dname()  { return $this->dname;  }
    public function get_loc()    { return $this->loc;    }

    public function set_deptno($v) { $this->deptno = $v; }
    public function set_dname($v)  { $this->dname  = $v; }
    public function set_loc($v)    { $this->loc     = $v; }
}

/* -----------------------------------------------
   Class for the EMP table
----------------------------------------------- */
class Emp {
    public $empno;
    public $ename;
    public $job;
    public $mgr;
    public $hiredate;
    public $sal;
    public $comm;
    public $deptno;

    public function __construct($empno=null,$ename=null,$job=null,$mgr=null,
                                 $hiredate=null,$sal=null,$comm=null,$deptno=null) {
        $this->empno    = $empno;
        $this->ename    = $ename;
        $this->job      = $job;
        $this->mgr      = $mgr;
        $this->hiredate = $hiredate;
        $this->sal      = $sal;
        $this->comm     = $comm;
        $this->deptno   = $deptno;
    }

    public function get_empno()    { return $this->empno;    }
    public function get_ename()    { return $this->ename;    }
    public function get_job()      { return $this->job;      }
    public function get_sal()      { return $this->sal;      }
    public function get_deptno()   { return $this->deptno;   }

    public function set_ename($v)  { $this->ename  = $v; }
    public function set_job($v)    { $this->job    = $v; }
    public function set_sal($v)    { $this->sal    = $v; }
    public function set_comm($v)   { $this->comm   = $v; }
    public function set_deptno($v) { $this->deptno = $v; }
}

/* -----------------------------------------------
   Class for the SALGRADE table
----------------------------------------------- */
class Salgrade {
    public $grade;
    public $losal;
    public $hisal;

    public function __construct($grade=null, $losal=null, $hisal=null) {
        $this->grade = $grade;
        $this->losal = $losal;
        $this->hisal = $hisal;
    }

    public function get_grade() { return $this->grade; }
    public function get_losal() { return $this->losal; }
    public function get_hisal() { return $this->hisal; }
}

/* -----------------------------------------------
   Class for the BONUS table
----------------------------------------------- */
class Bonus {
    public $ename;
    public $job;
    public $sal;
    public $comm;

    public function __construct($ename=null,$job=null,$sal=null,$comm=null) {
        $this->ename = $ename;
        $this->job   = $job;
        $this->sal   = $sal;
        $this->comm  = $comm;
    }
}

/* -----------------------------------------------
   Class for the Project table
----------------------------------------------- */
class Project {
    public $projno;
    public $projname;
    public $projtype;
    public $startdate;
    public $enddate;
    public $managerno;
    public $hrsrate;

    public function __construct($projno=null,$projname=null,$projtype=null,
                                 $startdate=null,$enddate=null,$managerno=null,$hrsrate=null) {
        $this->projno    = $projno;
        $this->projname  = $projname;
        $this->projtype  = $projtype;
        $this->startdate = $startdate;
        $this->enddate   = $enddate;
        $this->managerno = $managerno;
        $this->hrsrate   = $hrsrate;
    }
}

/* -----------------------------------------------
   Class for the ProjAssign table
----------------------------------------------- */
class ProjAssign {
    public $projno;
    public $empno;
    public $projperiod;
    public $noofhrs;

    public function __construct($projno=null,$empno=null,$projperiod=null,$noofhrs=null) {
        $this->projno     = $projno;
        $this->empno      = $empno;
        $this->projperiod = $projperiod;
        $this->noofhrs    = $noofhrs;
    }
}
?>